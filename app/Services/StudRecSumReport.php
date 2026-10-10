<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\StudentTransport;

/**
 * Stud_Rec_Sum — per-student fee ledger matching GAS INC_EXP columns:
 * ADM NO. | NAME | FATHER | ADDRESS | MOBILE | CLASS | VEHICLE |
 * TOT_PMNT | REG | ADM | ANN_PMNT | ANN_DUES | TUI_PMNT | TUI CALC | TUI_DUES |
 * TRA_PMNT | TRA CALC | TRA_DUES | DUES
 *
 * Payments mirror INCOME sheet SUMIFS; CALC/DUES use fee structure + transport fare
 * (Excel style: DUES columns = paid − expected, so a negative number is money still owed).
 *
 * Shared by the Global Workbook export (Stud_Rec_Sum sheet) and Fee Management › Demand Slip,
 * so both always show the same numbers.
 */
class StudRecSumReport
{
    /** Column key => Excel header, in Stud_Rec_Sum order. */
    public const COLUMNS = [
        'session' => 'SESSION',
        'admission_no' => 'ADM NO.',
        'name' => 'NAME OF STUDENT',
        'father_name' => 'FATHER NAME',
        'address' => 'ADDRESS',
        'mobile' => 'MOBILE',
        'class' => 'CLASS',
        'vehicle' => 'VEHICLE',
        'tot_pmnt' => 'TOT_PMNT',
        'reg' => 'REG',
        'adm' => 'ADM',
        'ann_pmnt' => 'ANN_PMNT',
        'ann_dues' => 'ANN_DUES',
        'tui_pmnt' => 'TUI_PMNT',
        'tui_calc' => 'TUI CALC',
        'tui_dues' => 'TUI_DUES',
        'tra_pmnt' => 'TRA_PMNT',
        'tra_calc' => 'TRA CALC',
        'tra_dues' => 'TRA_DUES',
        'dues' => 'DUES',
    ];

    /** Numeric (money) columns — totalled and right-aligned. */
    public const AMOUNT_COLUMNS = [
        'tot_pmnt', 'reg', 'adm', 'ann_pmnt', 'ann_dues', 'tui_pmnt', 'tui_calc', 'tui_dues',
        'tra_pmnt', 'tra_calc', 'tra_dues', 'dues',
    ];

    /**
     * One row per student on the session roster, keyed by COLUMNS plus student_id /
     * school_class_id / section_id / section for filtering.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(?AcademicSession $session): array
    {
        if (! $session) {
            return [];
        }

        $sessionLabel = self::shortSessionLabel($session->name);

        // Scoped to the chosen Academic Session — same roster rule
        // FeeReportCalculator::studentsForSessions() uses: a student counts for this session if
        // they have a session-history row naming it, or (only when this IS the current session)
        // they have no session-history rows at all yet (freshly admitted, never imported/promoted).
        $aliases = AcademicSession::nameAliases($session->name);
        $students = Student::query()
            ->with([
                'father:id,name',
                'schoolClass:id,name',
                'section:id,name',
                'udiseDetail:id,student_id,vehicle,stoppage',
            ])
            ->where(function ($q) use ($aliases, $session) {
                $q->whereHas('sessionHistories', fn ($h) => $h->whereIn('session', $aliases));
                if ($session->is_current) {
                    $q->orWhereDoesntHave('sessionHistories');
                }
            })
            ->orderBy('admission_no')
            ->get();

        if ($students->isEmpty()) {
            return [];
        }

        $studentIds = $students->pluck('id')->all();

        $vehicleByStudent = StudentTransport::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'Active')
            ->with(['route:id,vehicle_id', 'route.vehicle:id,vehicle_no', 'routeStop:id,fare'])
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');

        $headCodeById = FeeHead::query()->get(['id', 'name'])
            ->mapWithKeys(fn (FeeHead $h) => [(string) $h->id => self::headCode((string) $h->name)])
            ->all();

        $emptyPaid = ['TOT' => 0.0, 'REG' => 0.0, 'ADM' => 0.0, 'ANN' => 0.0, 'TUI' => 0.0, 'TRA' => 0.0];
        $paidByStudent = array_fill_keys($studentIds, $emptyPaid);

        $payments = FeePayment::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_session_id', $session->id)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
            })
            ->orderBy('id')
            ->get(['id', 'student_id', 'items', 'amount', 'refunded_amount', 'fine_amount', 'status']);

        foreach ($payments as $payment) {
            $sid = (int) $payment->student_id;
            if (! isset($paidByStudent[$sid])) {
                continue;
            }

            $gross = (float) $payment->amount;
            $net = $gross - (float) $payment->refunded_amount;
            if ($net <= 0 || $gross <= 0) {
                continue;
            }
            $scale = $net / $gross;

            $items = is_array($payment->items) ? $payment->items : [];
            if ($items === [] && $net > 0 && (float) $payment->fine_amount <= 0) {
                $items[] = ['fee_head_name' => 'Fee', 'amount' => $gross];
            }

            foreach ($items as $item) {
                $amount = (float) ($item['amount'] ?? 0) * $scale;
                if ($amount == 0.0) {
                    continue;
                }
                $feeHeadId = (int) ($item['fee_head_id'] ?? 0);
                $code = $feeHeadId && isset($headCodeById[(string) $feeHeadId])
                    ? $headCodeById[(string) $feeHeadId]
                    : self::headCode(trim((string) ($item['fee_head_name'] ?? 'Fee')) ?: 'Fee');
                $paidByStudent[$sid]['TOT'] += $amount;
                if (isset($paidByStudent[$sid][$code])) {
                    $paidByStudent[$sid][$code] += $amount;
                }
            }

            $fine = (float) $payment->fine_amount * $scale;
            if ($fine > 0) {
                $paidByStudent[$sid]['TOT'] += $fine;
            }
        }

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($students, $session);

        $monthKeys = collect($session->months())->map(fn ($m) => $m['key'] ?? null)->filter()->values()->all();
        if ($monthKeys === [] && $session->start_date && $session->end_date) {
            $cursor = $session->start_date->copy()->startOfMonth();
            $end = $session->end_date->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $monthKeys[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        }
        $monthCount = count($monthKeys);

        $rows = [];
        foreach ($students as $student) {
            $paid = $paidByStudent[$student->id] ?? $emptyPaid;

            // Structure charges only — no per-student payment re-query (was the 120s bottleneck).
            $charge = self::charges($student, $session, $monthKeys, $monthCount, $vehicleByStudent->get($student->id));

            $annPmnt = round($paid['ANN'], 2);
            $tuiPmnt = round($paid['TUI'], 2);
            $traPmnt = round($paid['TRA'], 2);
            $annDues = round($annPmnt - round($charge['ANN'], 2), 2);
            $tuiCalc = round($charge['TUI'], 2);
            $traCalc = round($charge['TRA'], 2);
            $tuiDues = round($tuiPmnt - $tuiCalc, 2);
            $traDues = round($traPmnt - $traCalc, 2);

            $vehicle = trim((string) ($student->udiseDetail?->vehicle ?? ''));
            if ($vehicle === '') {
                $vehicle = trim((string) ($vehicleByStudent->get($student->id)?->route?->vehicle?->vehicle_no ?? ''));
            }
            if ($vehicle === '') {
                $vehicle = 'None';
            }

            $rows[] = [
                'student_id' => $student->id,
                'school_class_id' => $student->school_class_id,
                'section_id' => $student->section_id,
                'section' => $student->section->name ?? '',
                'session' => $sessionLabel,
                'admission_no' => $student->admission_no ?? '',
                'name' => $student->name ?? '',
                'father_name' => $student->father?->name ?? '',
                'address' => trim(implode(' ', array_filter([$student->address ?? null, $student->address_line_2 ?? null]))),
                'mobile' => $student->mobile ?? '',
                'class' => $student->schoolClass->name ?? '',
                'vehicle' => $vehicle,
                'tot_pmnt' => round($paid['TOT'], 2),
                'reg' => round($paid['REG'], 2),
                'adm' => round($paid['ADM'], 2),
                'ann_pmnt' => $annPmnt,
                'ann_dues' => $annDues,
                'tui_pmnt' => $tuiPmnt,
                'tui_calc' => $tuiCalc,
                'tui_dues' => $tuiDues,
                'tra_pmnt' => $traPmnt,
                'tra_calc' => $traCalc,
                'tra_dues' => $traDues,
                'dues' => round($annDues + $tuiDues + $traDues, 2),
            ];
        }

        return $rows;
    }

    /**
     * Expected REG/ADM/ANN/TUI/TRA charges (no DB hits beyond FeeCalculator cache).
     *
     * @param  list<string>  $monthKeys
     * @return array{REG: float, ADM: float, ANN: float, TUI: float, TRA: float}
     */
    private static function charges(Student $student, AcademicSession $session, array $monthKeys, int $monthCount, ?StudentTransport $transport): array
    {
        $charge = ['REG' => 0.0, 'ADM' => 0.0, 'ANN' => 0.0, 'TUI' => 0.0, 'TRA' => 0.0];

        foreach (FeeCalculator::breakdownOnly($student, $session) as $plan) {
            $code = self::headCode((string) ($plan['fee_head_name'] ?? ''));
            if (! isset($charge[$code])) {
                continue;
            }
            $unit = (float) ($plan['amount'] ?? 0);
            if ($unit <= 0) {
                continue;
            }
            $freq = strtolower(str_replace(' ', '_', (string) ($plan['frequency'] ?? 'one_time')));
            if (in_array($freq, ['annual', 'one_time'], true)) {
                $charge[$code] += $unit;
            } elseif ($freq === 'quarterly') {
                $n = 0;
                foreach ($monthKeys as $monthKey) {
                    if (in_array((int) substr((string) $monthKey, 5, 2), [4, 7, 10, 1], true)) {
                        $n++;
                    }
                }
                $charge[$code] += $unit * $n;
            } else {
                $charge[$code] += $unit * $monthCount;
            }
        }

        $fare = (float) ($transport?->routeStop?->fare ?? 0);
        if ($fare > 0 && $monthCount > 0) {
            $feeStart = $transport?->feeStartMonthKey();
            $billable = 0;
            foreach ($monthKeys as $monthKey) {
                if ($feeStart && (string) $monthKey < (string) $feeStart) {
                    continue;
                }
                $billable++;
            }
            $charge['TRA'] += $fare * $billable;
        }

        return $charge;
    }

    /** Fee head name -> workbook code (reverse of GlobalWorkbookImportController::INCOME_HEAD_CANONICAL_MAP). */
    public static function headCode(string $headName): string
    {
        $map = [
            'registration fee' => 'REG',
            'admission fee' => 'ADM',
            'session fee' => 'ANN',
            'tution fee' => 'TUI',
            'tuition fee' => 'TUI',
            'transport' => 'TRA',
            'fine' => 'FINE',
            'transfer certificate fee' => 'TC',
            'tc fee' => 'TC',
        ];

        $key = strtolower(trim($headName));

        return $map[$key] ?? (strlen($headName) <= 12 ? strtoupper($headName) : $headName);
    }

    /** "2026-2027" -> "2026-27" (Global Workbook display form); anything else passes through unchanged. */
    public static function shortSessionLabel(string $label): string
    {
        return preg_match('/^(\d{4})-(\d{4})$/', trim($label), $m)
            ? $m[1].'-'.substr($m[2], -2)
            : $label;
    }
}
