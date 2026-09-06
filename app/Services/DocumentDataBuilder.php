<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Exam;
use App\Models\ExamAdmitInstruction;
use App\Models\ExamSchedule;
use App\Models\ExamScheduleSheet;
use App\Models\FeePayment;
use App\Models\IdCard;
use App\Models\LibraryMember;
use App\Models\SalarySlip;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\Teacher;
use App\Services\FeeCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Builds Template Builder placeholder maps from live ERP records.
 * Controllers only call these helpers — layout lives entirely in templates.
 */
class DocumentDataBuilder
{
    /**
     * Derives a small palette (header fill, dark text, light tint, border, zebra stripe) from a
     * single school-chosen accent hex so Exam Schedule / Report Card / Admit Card PDFs can be
     * recoloured per exam without every template needing its own colour picker.
     */
    public function accentPalette(?string $hex): array
    {
        // "none" is a deliberate choice (the PDF colour picker's "None" button), distinct from
        // null/unset which falls back to the default blue below — it renders every accent slot
        // in greyscale so the PDF prints cleanly on a black & white printer.
        if ($hex === 'none') {
            return [
                // Pure black & white — no tinted fills, no white-on-dark bars.
                'accent_color' => '#000000',
                'accent_dark' => '#000000',
                'accent_light' => '#ffffff',
                'accent_border' => '#000000',
                'accent_zebra' => '#ffffff',
                'accent_bar_bg' => '#ffffff',
                'accent_bar_text' => '#000000',
                'accent_bar_text_soft' => '#000000',
                'accent_bar_text_softer' => '#333333',
            ];
        }

        $hex = ($hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) ? $hex : '#1e3a5f';
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $mix = function (float $amount, array $target) use ($r, $g, $b) {
            $nr = (int) round($r + ($target[0] - $r) * $amount);
            $ng = (int) round($g + ($target[1] - $g) * $amount);
            $nb = (int) round($b + ($target[2] - $b) * $amount);

            return sprintf('#%02x%02x%02x', $nr, $ng, $nb);
        };

        return [
            'accent_color' => $hex,
            'accent_dark' => $mix(0.35, [0, 0, 0]),
            'accent_light' => $mix(0.90, [255, 255, 255]),
            'accent_border' => $mix(0.65, [255, 255, 255]),
            'accent_zebra' => $mix(0.96, [255, 255, 255]),
            'accent_bar_bg' => $hex,
            'accent_bar_text' => '#ffffff',
            'accent_bar_text_soft' => 'rgba(255,255,255,0.85)',
            'accent_bar_text_softer' => 'rgba(255,255,255,0.7)',
        ];
    }

    public function schoolContext(): array
    {
        $school = SchoolSetting::current();
        $address = $school->formatted_address;
        $principal = $school->findSignature('Principal');
        $examController = $school->findSignature('Examination Controller');
        $firstStamp = collect($school->stamps ?? [])->first();

        return [
            'school_name' => $school->school_name ?: 'School',
            'school_code' => $school->school_code ?? '',
            'school_address' => $address,
            'school_phone' => $school->phone ?? '',
            'school_email' => $school->email ?? '',
            'school_website' => $school->website ?? '',
            'registration_no' => $school->registration_no ?? '',
            'udise_code' => $school->udise_code ?? '',
            'school_logo' => $this->resolveStoredImage($school->logo_path),
            'school_banner' => $this->resolveStoredImage($school->banner_path),
            'school_stamp' => $this->resolveStoredImage($firstStamp['image_path'] ?? null),
            'principal_signature' => $principal['label'] ?? 'Principal',
            'principal_signature_image' => $this->resolveStoredImage($principal['image_path'] ?? null),
            'signature_line' => $examController['label'] ?? 'Controller of Examinations',
            'signature_image' => $this->resolveStoredImage($examController['image_path'] ?? null),
            'branch' => $school->current_branch ?? '',
            'session_year' => AcademicSession::where('is_current', true)->value('name') ?? '',
        ];
    }

    public function feeReceipt(FeePayment $payment): array
    {
        $payment->loadMissing([
            'student:id,name,admission_no,roll_no,city,branch_id,school_class_id,section_id,father_id',
            'student.branch:id,name',
            'student.schoolClass:id,name',
            'student.section:id,name',
            'student.father:id,name',
            'collectedBy:id,name',
            'academicSession:id,name',
        ]);

        $student = $payment->student;
        $session = $payment->academicSession;
        $paidNet = round((float) $payment->amount - (float) $payment->refunded_amount, 2);

        $itemsDiscount = collect($payment->items ?? [])->sum(fn ($i) => (float) ($i['discount'] ?? 0));

        // Receipt totals: structure charge for months on this receipt, minus what was
        // already paid earlier for those same fee heads/months. So a ₹1,000 follow-up
        // on a ₹2,000 monthly fee shows Total Fees ₹1,000 (remaining), not ₹2,000 again.
        $calc = FeeCalculator::forStudent($student, $session);
        $breakdown = collect($calc['breakdown'] ?? []);
        $byHead = $breakdown->keyBy(fn ($r) => (int) ($r['fee_head_id'] ?? 0));

        $unitsForFee = function (string $frequency, array $itemMonths): int {
            $freq = strtolower(str_replace(' ', '_', (string) ($frequency ?: 'monthly')));
            $months = array_values($itemMonths);
            if ($freq === 'monthly') {
                return count($months);
            }
            if ($freq === 'quarterly') {
                $quarterMonths = [4, 7, 10, 1];
                $matched = 0;
                foreach ($months as $m) {
                    $key = substr((string) $m, 0, 7);
                    if (preg_match('/^\d{4}-\d{2}$/', $key)) {
                        $monthNum = (int) substr($key, 5, 2);
                        if (in_array($monthNum, $quarterMonths, true)) {
                            $matched++;
                        }
                    }
                }

                return $matched > 0 ? $matched : (count($months) ? 1 : 0);
            }

            return count($months) ? 1 : 0;
        };

        $priorQuery = FeePayment::query()
            ->where('student_id', $payment->student_id)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
            })
            ->where('id', '<', $payment->id);

        if ($session) {
            $priorQuery->where('academic_session_id', $session->id);
        }

        $unitByHead = [];
        $freqByHead = [];
        foreach ($breakdown as $row) {
            $hid = (int) ($row['fee_head_id'] ?? 0);
            if (! $hid) {
                continue;
            }
            $unitByHead[$hid] = (float) ($row['amount'] ?? 0);
            $freqByHead[$hid] = strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
        }

        $transportAssignment = StudentTransport::query()
            ->where('student_id', $payment->student_id)
            ->where('status', 'Active')
            ->with('routeStop:id,fare')
            ->first();
        $transportFare = (float) ($transportAssignment?->routeStop?->fare ?? 0);
        if ($transportFare > 0) {
            $transportHeadId = (int) (\App\Models\FeeHead::query()->where('name', 'Transport')->value('id') ?? 0);
            if ($transportHeadId) {
                $unitByHead[$transportHeadId] = $transportFare;
                $freqByHead[$transportHeadId] = 'monthly';
            }
        }

        $priorByHead = [];
        $priorByHeadMonth = [];
        foreach ($priorQuery->orderBy('id')->get(['items', 'amount', 'refunded_amount']) as $prev) {
            $gross = (float) $prev->amount;
            $net = $gross - (float) $prev->refunded_amount;
            if ($net <= 0 || $gross <= 0) {
                continue;
            }
            $scale = $net / $gross;

            foreach ($prev->items ?? [] as $prevItem) {
                $headId = (int) ($prevItem['fee_head_id'] ?? 0);
                if (! $headId) {
                    continue;
                }
                $paidAmt = (float) ($prevItem['amount'] ?? 0) * $scale;
                $discAmt = (float) ($prevItem['discount'] ?? 0) * $scale;
                $covered = $paidAmt + $discAmt;
                $priorByHead[$headId] = ($priorByHead[$headId] ?? 0.0) + $covered;

                $prevMonths = [];
                if (! empty($prevItem['months']) && is_array($prevItem['months'])) {
                    $prevMonths = $prevItem['months'];
                } elseif (! empty($prevItem['month'])) {
                    $prevMonths = [$prevItem['month']];
                }
                if ($prevMonths === []) {
                    continue;
                }

                $already = [];
                foreach (array_keys($priorByHeadMonth[$headId] ?? []) as $mk) {
                    $already[$mk] = (float) ($priorByHeadMonth[$headId][$mk] ?? 0);
                }

                $shares = FeeMonthAllocator::distribute(
                    $paidAmt,
                    $discAmt,
                    $prevMonths,
                    (float) ($unitByHead[$headId] ?? 0),
                    $already,
                    $freqByHead[$headId] ?? 'monthly'
                );

                foreach ($shares as $monthKey => $share) {
                    $priorByHeadMonth[$headId][$monthKey] = ($priorByHeadMonth[$headId][$monthKey] ?? 0.0)
                        + (float) $share['paid']
                        + (float) $share['discount'];
                }
            }
        }

        $totalFees = 0.0;
        foreach ($payment->items ?? [] as $item) {
            $feeHeadId = (int) ($item['fee_head_id'] ?? 0);
            $itemAmount = (float) ($item['amount'] ?? 0);
            $itemDiscount = (float) ($item['discount'] ?? 0);
            $itemMonths = ! empty($item['months']) && is_array($item['months'])
                ? $item['months']
                : (! empty($item['month']) ? [$item['month']] : []);

            $storedCharge = (float) ($item['charge'] ?? 0);
            $plan = $feeHeadId ? $byHead->get($feeHeadId) : null;
            $frequency = $plan
                ? strtolower(str_replace(' ', '_', (string) ($plan['frequency'] ?? 'monthly')))
                : 'monthly';

            // Amount column = due left for this line at collection time.
            if ($storedCharge > 0) {
                $lineDue = round($storedCharge, 2);
            } elseif ($plan) {
                $basePerUnit = (float) ($plan['amount'] ?? 0);
                $units = $unitsForFee($frequency, $itemMonths);
                $full = round($basePerUnit * $units, 2);
                $priorCovered = 0.0;
                if (in_array($frequency, ['annual', 'one_time'], true)) {
                    $priorCovered = (float) ($priorByHead[$feeHeadId] ?? 0);
                } else {
                    foreach ($itemMonths as $month) {
                        $monthKey = substr((string) $month, 0, 7);
                        $priorCovered += (float) ($priorByHeadMonth[$feeHeadId][$monthKey] ?? 0);
                    }
                }
                $lineDue = max(0.0, round($full - $priorCovered, 2));
            } else {
                // Transport / ad-hoc without stored charge: reconstruct due from prior months.
                $priorCovered = 0.0;
                foreach ($itemMonths as $month) {
                    $monthKey = substr((string) $month, 0, 7);
                    $priorCovered += (float) ($priorByHeadMonth[$feeHeadId][$monthKey] ?? 0);
                }
                // Best effort: due = paid on this receipt + nothing known of full fare.
                $lineDue = max($itemAmount + $itemDiscount, round($itemAmount + $itemDiscount + 0, 2));
                if ($feeHeadId && $priorCovered <= 0) {
                    $lineDue = max($lineDue, $itemAmount + $itemDiscount);
                }
            }

            $totalFees += max($lineDue, $itemAmount + $itemDiscount);
        }
        $totalFees = round((float) $totalFees, 2);

        $lateFee = (float) $payment->fine_amount;
        $adjustment = (float) ($payment->discount_amount ?: $itemsDiscount);
        $backDues = 0.0;
        $grandTotal = round($totalFees + $backDues + $lateFee - $adjustment, 2);
        $balanceDues = max(0, round($grandTotal - $paidNet, 2));

        $months = collect($payment->items ?? [])
            ->flatMap(fn ($i) => ! empty($i['months']) ? $i['months'] : (! empty($i['month']) ? [$i['month']] : []))
            ->filter()->unique()->sort()->values();
        $feeForMonths = $months->map(function ($m) {
            try {
                return Carbon::createFromFormat('Y-m', (string) $m)->format('M');
            } catch (\Throwable) {
                return (string) $m;
            }
        })->implode(' ');
        if ($feeForMonths === '') {
            $feeForMonths = $payment->payment_date ? Carbon::parse($payment->payment_date)->format('M') : '';
        }

        $lines = [];
        $sno = 1;
        foreach ($payment->items ?? [] as $item) {
            $itemMonths = ! empty($item['months']) && is_array($item['months']) ? $item['months'] : (! empty($item['month']) ? [$item['month']] : []);
            $duration = $this->formatFeeDuration($itemMonths, $feeForMonths);

            $feeHeadId = (int) ($item['fee_head_id'] ?? 0);
            $paidAmt = (float) ($item['amount'] ?? 0);
            $storedCharge = (float) ($item['charge'] ?? 0);
            $plan = $feeHeadId ? $byHead->get($feeHeadId) : null;
            $frequency = $plan
                ? strtolower(str_replace(' ', '_', (string) ($plan['frequency'] ?? 'monthly')))
                : 'monthly';

            if ($storedCharge > 0) {
                $lineAmount = round($storedCharge, 2);
            } elseif ($plan) {
                $full = round(((float) ($plan['amount'] ?? 0)) * $unitsForFee($frequency, $itemMonths), 2);
                $priorCovered = 0.0;
                if (in_array($frequency, ['annual', 'one_time'], true)) {
                    $priorCovered = (float) ($priorByHead[$feeHeadId] ?? 0);
                } else {
                    foreach ($itemMonths as $month) {
                        $monthKey = substr((string) $month, 0, 7);
                        $priorCovered += (float) ($priorByHeadMonth[$feeHeadId][$monthKey] ?? 0);
                    }
                }
                $lineAmount = max(0.0, round($full - $priorCovered, 2));
            } else {
                $lineAmount = max($paidAmt, $storedCharge);
            }
            $lineAmount = max($lineAmount, $paidAmt);

            // Amount = due for this line; Paid = amount collected on this receipt.
            $amountCell = number_format($lineAmount, 2);

            $lines[] = sprintf(
                '%-3s %-16s %-11s %10s %10s',
                $sno++,
                mb_substr((string) ($item['fee_head_name'] ?? 'Fee'), 0, 16),
                $duration,
                $amountCell,
                number_format($paidAmt, 2)
            );
        }
        if ($lines === []) {
            $lines[] = sprintf(
                '%-3s %-16s %-11s %10s %10s',
                1,
                'Fee payment',
                mb_substr($feeForMonths, 0, 11),
                number_format($paidNet, 2),
                number_format($paidNet, 2)
            );
        }

        $classSection = trim(($student?->schoolClass?->name ?? '').($student?->section ? ' ('.$student->section->name.')' : ''));

        return array_merge($this->schoolContext(), [
            'receipt_title' => 'Fee Receipt',
            'receipt_no' => $payment->receipt_no ?? '',
            'receipt_date' => $payment->payment_date
                ? Carbon::parse($payment->payment_date)->format('d-m-Y')
                : now()->format('d-m-Y'),
            'student_name' => strtoupper((string) ($student?->name ?? '')),
            'father_name' => strtoupper((string) ($student?->father?->name ?? '')),
            'city' => strtoupper((string) ($student?->city ?? '')),
            'admission_id' => $student?->admission_no ?? '',
            'class' => $student?->schoolClass?->name ?? '',
            'section' => $student?->section?->name ?? '',
            'class_section' => $classSection,
            'fee_for_months' => $feeForMonths,
            'items_table' => "S.N Particulars      Duration      Amount       Paid\n".implode("\n", $lines),
            'total_fees' => number_format($totalFees, 2),
            'back_dues' => number_format($backDues, 2),
            'late_fee' => number_format($lateFee, 2),
            'adjustment' => number_format($adjustment, 2),
            'grand_total' => number_format($grandTotal, 2),
            'paid_amount' => number_format($paidNet, 2),
            'balance_dues' => number_format($balanceDues, 2),
            'amount_in_words' => $this->amountInWords($paidNet),
            'payment_mode' => strtoupper((string) $payment->payment_mode),
            'received_by' => $payment->collectedBy->name ?? 'Accounts Office',
            'remarks' => $payment->remarks ?? '',
        ]);
    }

    /**
     * Compact duration that fits the fixed 11-char receipt column
     * (avoids truncating "Apr 2026, May 2026" to "Apr 2026, M").
     *
     * @param  list<string|mixed>  $itemMonths
     */
    private function formatFeeDuration(array $itemMonths, string $fallback = ''): string
    {
        $keys = collect($itemMonths)
            ->map(fn ($m) => substr((string) $m, 0, 7))
            ->filter(fn ($k) => (bool) preg_match('/^\d{4}-\d{2}$/', $k))
            ->unique()
            ->sort()
            ->values();

        if ($keys->isEmpty()) {
            return mb_substr($fallback, 0, 11);
        }

        try {
            if ($keys->count() === 1) {
                return Carbon::createFromFormat('Y-m', $keys[0])->format('M Y');
            }

            $first = Carbon::createFromFormat('Y-m', $keys->first());
            $last = Carbon::createFromFormat('Y-m', $keys->last());

            if ($keys->count() === 2 && $first->year === $last->year) {
                // "Apr+May 26" (10 chars)
                return $first->format('M').'+'.$last->format('M').' '.$first->format('y');
            }

            if ($first->year === $last->year) {
                // "Apr-Jul 26"
                return $first->format('M').'-'.$last->format('M').' '.$first->format('y');
            }

            // Cross-year: "Apr26-Mar27"
            return $first->format('My').'-'.$last->format('My');
        } catch (\Throwable) {
            return mb_substr($fallback !== '' ? $fallback : (string) $keys->first(), 0, 11);
        }
    }

    /** @param  array<string, mixed>  $duePayload  JSON shape from FeeDueController's manualReceipt()/automaticReceipt() */
    public function feeDueReceipt(array $duePayload): array
    {
        $student = $duePayload['student'] ?? [];
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $lineAmount = function (array $line): float {
            if (array_key_exists('balance', $line)) {
                return (float) $line['balance'];
            }

            return (float) ($line['dues'] ?? $line['due'] ?? $line['amount'] ?? 0);
        };

        // Hide ₹0 dues. Group by month only — never merge same-named fee records.
        // Month groups are inline-block so DomPDF wraps them to use horizontal space.
        $dueGroups = collect($duePayload['lines'] ?? [])
            ->filter(fn ($line) => $lineAmount((array) $line) > 0.0001)
            ->groupBy(fn ($line) => (string) ($line['month_key'] ?? $line['duration'] ?? 'Other'))
            ->sortKeys()
            ->filter(fn ($group) => $group->isNotEmpty());

        if ($dueGroups->isEmpty()) {
            $dueTableHtml = '<div class="dues-empty">No outstanding dues</div>';
        } else {
            $blocks = $dueGroups->map(function ($group, $key) use ($e, $lineAmount) {
                $monthLabel = $this->shortMonthLabel(
                    (string) ($group->first()['month_key'] ?? ''),
                    (string) ($group->first()['duration'] ?? $key)
                );

                $fees = $group->values()->map(function ($line) use ($e, $lineAmount) {
                    $label = $this->shortFeeParticular((string) ($line['fee_particulars'] ?? $line['name'] ?? 'Fee'));
                    $amount = $lineAmount((array) $line);

                    return '<span class="dues-chip">'
                        .'<span class="dues-name">'.$e($label).'</span>'
                        .' <span class="dues-amt">₹'.$e(number_format($amount, 0)).'</span>'
                        .'</span>';
                })->implode('<span class="dues-sep">|</span>');

                return '<div class="dues-group">'
                    .'<span class="dues-month">'.$e($monthLabel).'</span>'
                    .'<span class="dues-fees">'.$fees.'</span>'
                    .'</div>';
            })->implode('');

            $dueTableHtml = '<div class="dues-flow">'.$blocks.'</div>';
        }

        // Payments: group only when BOTH date and payment mode match; sum those amounts.
        // Inline-block groups wrap horizontally like dues.
        $paymentGroups = collect($duePayload['payment_history'] ?? [])
            ->groupBy(fn ($p) => strtolower(trim((string) ($p['date'] ?? ''))).'|'.strtolower(trim((string) ($p['payment_mode'] ?? ''))))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'date' => trim((string) ($first['date'] ?? '')),
                    'mode' => $this->titleCaseMode((string) ($first['payment_mode'] ?? '')),
                    'amount' => round((float) $group->sum(fn ($p) => (float) ($p['amount'] ?? 0)), 2),
                ];
            })
            ->filter(fn (array $g) => $g['amount'] > 0.0001)
            ->values();

        if ($paymentGroups->isEmpty()) {
            $paymentHtml = '<div class="pay-empty">No payments recorded yet.</div>';
        } else {
            $blocks = $paymentGroups->map(function (array $g) use ($e) {
                return '<div class="pay-group">'
                    .'<span class="pay-date">'.$e($g['date']).'</span>'
                    .'<span class="pay-pipe">|</span>'
                    .'<span class="pay-mode">'.$e($g['mode']).'</span>'
                    .'<span class="pay-pipe">|</span>'
                    .'<span class="pay-amt">₹'.$e(number_format($g['amount'], 0)).'</span>'
                    .'</div>';
            })->implode('');
            $paymentHtml = '<div class="pay-flow">'.$blocks.'</div>';
        }

        $classSection = trim(($student['class'] ?? '').(! empty($student['section']) ? ' ('.$student['section'].')' : ''));
        // Use controller's remaining_due as-is — display-only change, no recalculation.
        $totalDue = (float) ($duePayload['remaining_due'] ?? $duePayload['total_due'] ?? $duePayload['amount_due'] ?? 0);

        return array_merge($this->schoolContext(), [
            'receipt_title' => $duePayload['receipt_title'] ?? 'Fee Due Receipt',
            'receipt_no' => $duePayload['receipt_no'] ?? '',
            'receipt_date' => $duePayload['printed_at'] ?? now()->format('j M Y'),
            'student_name' => $student['name'] ?? '',
            'father_name' => $student['father'] ?? '',
            'mother_name' => $student['mother'] ?? '',
            'roll_number' => $student['roll_no'] ?? '',
            'admission_id' => $student['admission_no'] ?? '',
            'class' => $student['class'] ?? '',
            'section' => $student['section'] ?? '',
            'class_section' => $classSection,
            'due_table' => $dueTableHtml,
            'total_due' => '₹'.number_format($totalDue, 0),
            'due_date' => (string) ($duePayload['due_date'] ?? ''),
            'payment_history_table' => $paymentHtml,
            'remarks' => $duePayload['remarks'] ?? $duePayload['description'] ?? 'Please clear dues before the due date.',
        ]);
    }

    /** "2026-04" / "April 2026" → "APR 2026". */
    private function shortMonthLabel(string $monthKey, string $fallback): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            try {
                return strtoupper(\Carbon\Carbon::createFromFormat('Y-m', $monthKey)->format('M Y'));
            } catch (\Throwable) {
                // fall through
            }
        }
        if (preg_match('/^([A-Za-z]+)\s+(\d{4})$/', trim($fallback), $m)) {
            return strtoupper(substr($m[1], 0, 3)).' '.$m[2];
        }

        return strtoupper(trim($fallback)) ?: 'OTHER';
    }

    /** Shorten "ADMISSION FEE" → "Admission" for compact due slips. */
    private function shortFeeParticular(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $name = preg_replace('/\s*\(QUARTERLY\)\s*/i', '', $name) ?? $name;
        $name = preg_replace('/\s+FEE$/i', '', $name) ?? $name;

        return $name === '' ? 'Fee' : mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
    }

    private function titleCaseMode(string $mode): string
    {
        $mode = trim(preg_replace('/\s+/', ' ', $mode) ?? $mode);
        if ($mode === '') {
            return 'Other';
        }

        return mb_convert_case(mb_strtolower($mode), MB_CASE_TITLE, 'UTF-8');
    }

    public function admitCard(Exam $exam, Student $student, array $overrides = []): array
    {
        $student->loadMissing(['schoolClass:id,name', 'section:id,name', 'father:id,name', 'mother:id,name', 'documents']);

        // Class Head — the teacher assigned to the student's class (teachers.school_class_id),
        // independent of section. Same lookup used for the certificate PDFs.
        $classTeacher = $student->school_class_id
            ? Teacher::where('school_class_id', $student->school_class_id)->orderBy('id')->first()
            : null;

        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $scheduleSheet = ExamScheduleSheet::where('exam_id', $exam->id)->where('branch_id', $student->branch_id)
            ->with('dates.cells.subject:id,name')
            ->first();

        // One date row: Date | Day | Sitting | Subject | Sitting | Subject | …
        // No Timing. Extra sittings wrap onto continuation rows (blank Date/Day).
        $scheduleTextRows = collect();
        $scheduleTableHtml = '';
        $maxPairsPerRow = 3;

        $dateBlocks = [];
        if ($scheduleSheet) {
            foreach ($scheduleSheet->dates as $date) {
                if ($date->is_holiday) {
                    continue;
                }
                $entries = $date->cells
                    ->filter(fn ($c) => $c->school_class_id === $student->school_class_id && $c->section_id === $student->section_id)
                    ->values();
                if ($entries->isEmpty()) {
                    continue;
                }

                $pairs = $entries->values()->map(function ($entry, int $i) {
                    return [
                        'sitting' => (string) ($i + 1),
                        'subject' => trim((string) ($entry->subject->name ?? '')) ?: '—',
                    ];
                });

                $dateBlocks[] = [
                    'date' => $date->date->format('d M Y'),
                    'day' => $date->date->format('l'),
                    'pairs' => $pairs,
                ];

                $scheduleTextRows->push(sprintf(
                    '%-12s %-10s %s',
                    $date->date->format('d M Y'),
                    $date->date->format('l'),
                    $pairs->map(fn ($p) => $p['sitting'].' '.$p['subject'])->implode('  |  ')
                ));
            }
        }

        if ($dateBlocks === []) {
            $scheduleTableHtml = '<table class="sched"><thead><tr>'
                .'<th>Date</th><th>Day</th><th class="center">Sitting</th><th>Subject</th>'
                .'</tr></thead><tbody>'
                .'<tr><td colspan="4" class="empty">No schedule published yet.</td></tr>'
                .'</tbody></table>';
        } else {
            $maxPairs = max(array_map(fn ($b) => $b['pairs']->count(), $dateBlocks));
            $colsPerRow = max(1, min($maxPairsPerRow, $maxPairs));

            $thead = '<th>Date</th><th>Day</th>';
            for ($i = 0; $i < $colsPerRow; $i++) {
                $thead .= '<th class="center">Sitting</th><th>Subject</th>';
            }

            $tbody = '';
            foreach ($dateBlocks as $block) {
                $chunks = $block['pairs']->chunk($colsPerRow)->values();
                foreach ($chunks as $chunkIndex => $chunk) {
                    $tbody .= '<tr>';
                    if ($chunkIndex === 0) {
                        $tbody .= '<td class="dt">'.$escape($block['date']).'</td>'
                            .'<td class="day">'.$escape($block['day']).'</td>';
                    } else {
                        $tbody .= '<td class="dt"></td><td class="day"></td>';
                    }
                    $chunk = $chunk->values();
                    for ($i = 0; $i < $colsPerRow; $i++) {
                        $pair = $chunk->get($i);
                        $tbody .= '<td class="center sit">'.$escape($pair['sitting'] ?? '').'</td>'
                            .'<td class="sub">'.$escape($pair['subject'] ?? '').'</td>';
                    }
                    $tbody .= '</tr>';
                }
            }

            $scheduleTableHtml = '<table class="sched"><thead><tr>'.$thead.'</tr></thead><tbody>'.$tbody.'</tbody></table>';
        }

        // Preserve the admin's rich-text formatting (bold/lists) from the "General Instructions"
        // editor instead of stripping it to plain text — only a safe formatting subset is kept.
        $instructionsRaw = ExamAdmitInstruction::where('exam_id', $exam->id)->value('instructions');
        $generalInstructions = $instructionsRaw !== null && trim(strip_tags($instructionsRaw)) !== ''
            ? strip_tags($instructionsRaw, '<b><strong><i><em><u><ul><ol><li><br><p>')
            : '<p>Bring this admit card and school ID to every exam. Reach the center 30 minutes early.</p>';
        $generalInstructions = $this->compactAdmitInstructions($generalInstructions);

        $school = $this->schoolContext();
        // Two header lines, matching the reference layout: address + phone + email on one
        // line, affiliation/registration + U-DISE code on the next — not pre-escaped here
        // since the template prints both through Blade's auto-escaping {{ }}.
        $addressBits = array_filter([
            $school['school_address'] ?: null,
            ! empty($school['school_phone']) ? 'Phone: '.$school['school_phone'] : null,
            ! empty($school['school_email']) ? 'Email: '.$school['school_email'] : null,
        ]);
        $affiliationBits = array_filter([
            ! empty($school['registration_no']) ? 'Registration No: '.$school['registration_no'] : null,
            ! empty($school['udise_code']) ? 'U-DISE Code: '.$school['udise_code'] : null,
        ]);

        $photoUri = $this->resolveStoredImage($student->documents?->photo_path);
        $classTeacherSig = $this->resolveStoredImage($classTeacher?->signature_path);
        $controllerSig = (string) ($school['signature_image'] ?? '');
        $principalSig = (string) ($school['principal_signature_image'] ?? '');

        return array_merge($school, $this->accentPalette($exam->pdf_accent_color), [
            'mono_class' => $exam->pdf_accent_color === 'none' ? 'mono' : '',
            'exam_title' => $exam->name,
            'student_name' => $overrides['name'] ?? $student->name,
            'admission_id' => $overrides['admission_no'] ?? $student->admission_no,
            'roll_number' => $overrides['roll_no'] ?? ($student->roll_no ?? ''),
            'class' => $student->schoolClass->name ?? '',
            'section' => $student->section->name ?? '',
            'gender' => $student->gender ?? '',
            'blood_group' => $student->blood_group ?? '',
            'father_name' => $overrides['father_name'] ?? ($student->father->name ?? ''),
            'mother_name' => $overrides['mother_name'] ?? ($student->mother->name ?? ''),
            'dob' => $student->dob ? Carbon::parse($student->dob)->format('d M Y') : '',
            'school_meta_line' => implode('  |  ', $affiliationBits),
            'school_contact_line' => implode('  |  ', $addressBits),
            'schedule_table' => "Date         Day        Sitting  Subject\n".($scheduleTextRows->implode("\n") ?: 'No schedule published yet.'),
            'schedule_table_html' => $scheduleTableHtml,
            'general_instructions' => $generalInstructions,
            'photo' => $photoUri,
            'photo_html' => $this->inlineImageHtml($photoUri),
            'class_teacher_name' => $classTeacher->name ?? '',
            'class_teacher_signature_image' => $classTeacherSig,
            'class_teacher_sign_html' => $this->inlineImageHtml($classTeacherSig, 'sig-img'),
            'exam_controller_sign_html' => $this->inlineImageHtml($controllerSig, 'sig-img'),
            'principal_sign_html' => $this->inlineImageHtml($principalSig, 'sig-img'),
        ]);
    }

    /** @param  array<string, mixed>  $row  from ExamResultCalculator */
    public function reportCard(Exam $exam, array $row): array
    {
        return app(ReportCardPdfService::class)->buildData($exam, $row);
    }

    public function examSchedule(ExamScheduleSheet $sheet): array
    {
        $sheet->loadMissing([
            'exam:id,name,pdf_accent_color',
            'dates.cells.schoolClass:id,name,sort_order',
            'dates.cells.section:id,name',
            'dates.cells.subject:id,name',
        ]);

        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $school = $this->schoolContext();
        $sessionName = AcademicSession::fromRequest(request(), true)?->name
            ?: ($school['session_year'] ?? '');

        // Columns: one per (class, section) actually used, ordered by the class's pedagogical
        // sort_order (Nursery, LKG, UKG, 1, 2, ... 12) rather than raw class id. Section is
        // deliberately left out of the label — only used here to keep two sections of the same
        // class as distinct columns if a sheet happens to have both.
        $columns = [];
        foreach ($sheet->dates as $date) {
            foreach ($date->cells as $cell) {
                $key = $cell->school_class_id.'-'.$cell->section_id;
                $columns[$key] ??= [
                    'label' => $cell->schoolClass->name ?? '',
                    'sort_order' => $cell->schoolClass->sort_order ?? 0,
                    'section_name' => $cell->section->name ?? '',
                ];
            }
        }
        uasort($columns, fn ($a, $b) => [$a['sort_order'], $a['label'], $a['section_name']] <=> [$b['sort_order'], $b['label'], $b['section_name']]);

        $headerRow = '<th class="col-date">Date</th><th class="col-sitting">Sitting</th>'
            .collect($columns)->map(fn ($col) => '<th>'.$escape($col['label']).'</th>')->implode('');

        $bodyRows = $sheet->dates->map(function ($date) use ($columns, $escape) {
            $cellsByColumn = collect($date->cells)->groupBy(fn ($c) => $c->school_class_id.'-'.$c->section_id);

            if ($date->is_holiday) {
                $cells = str_repeat('<td class="holiday">Holiday</td>', max(1, count($columns)));

                return '<tr><td class="col-date">'.$date->date->format('d M Y').'<div class="dow">'.$date->date->format('D').'</div></td>'
                    .'<td class="col-sitting">—</td>'.$cells.'</tr>';
            }

            $entriesByColumn = collect(array_keys($columns))
                ->mapWithKeys(fn ($key) => [$key => $cellsByColumn->get($key, collect())->values()]);
            $maxEntries = (int) $entriesByColumn->map(fn ($c) => $c->count())->max();
            // Fully data-driven: exactly as many sitting rows as the day's busiest class
            // actually has entries for — never a fabricated minimum. A class with only one
            // subject entered for a date (e.g. Nursery's single "Oral Rhymes" sitting) prints
            // exactly that, with no invented second "Oral" row.
            $rowsToRender = max(1, $maxEntries);

            $rows = '';
            for ($i = 0; $i < $rowsToRender; $i++) {
                $rows .= '<tr>';
                if ($i === 0) {
                    $rows .= '<td class="col-date" rowspan="'.$rowsToRender.'">'.$date->date->format('d M Y').'<div class="dow">'.$date->date->format('D').'</div></td>';
                }
                $rows .= '<td class="col-sitting">'.($i + 1).'</td>';
                foreach (array_keys($columns) as $key) {
                    $entries = $entriesByColumn[$key];
                    $entry = $entries[$i] ?? null;
                    if ($entry) {
                        $rows .= '<td class="subject-cell">'.$escape($entry->subject->name ?? '').'</td>';
                    } else {
                        $rows .= '<td class="empty">'.($i === 0 ? '—' : '').'</td>';
                    }
                }
                $rows .= '</tr>';
            }

            return $rows;
        })->implode('');

        $colCount = max(1, count($columns) + 2);
        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="'.$colCount.'" class="empty">No dates added.</td></tr>';
        }

        $metaBits = array_filter([
            ! empty($school['registration_no']) ? 'Reg No.: '.$escape($school['registration_no']) : null,
            ! empty($school['udise_code']) ? 'U-DISE: '.$escape($school['udise_code']) : null,
        ]);
        $contactBits = array_filter([
            ! empty($school['school_phone']) ? 'Ph: '.$escape($school['school_phone']) : null,
            ! empty($school['school_email']) ? $escape($school['school_email']) : null,
            ! empty($school['school_website']) ? $escape($school['school_website']) : null,
        ]);

        return array_merge($school, $this->accentPalette($sheet->exam->pdf_accent_color ?? null), [
            'mono_class' => ($sheet->exam->pdf_accent_color ?? null) === 'none' ? 'mono' : '',
            'schedule_title' => 'Examination Schedule',
            'exam_title' => $sheet->exam->name ?? 'Exam',
            'session_year' => $sessionName ?: '—',
            'school_meta_line' => implode('  |  ', $metaBits),
            'school_contact_line' => implode('  ·  ', $contactBits),
            'logo_html' => ! empty($school['school_logo'])
                ? '<img class="logo" src="'.$school['school_logo'].'" alt="Logo" />'
                : '<div class="logo-fallback">'.htmlspecialchars(mb_strtoupper(mb_substr($school['school_name'] ?? 'S', 0, 2)), ENT_QUOTES, 'UTF-8').'</div>',
            'schedule_header_html' => $headerRow,
            'schedule_body_html' => $bodyRows,
            'schedule_table' => strip_tags(str_replace(['</tr>', '</th>', '</td>'], ["\n", ' | ', ' | '], $headerRow."\n".$bodyRows)),
        ]);
    }

    /**
     * @param  array<string, string>  $overrides  Raw values from the "Prepare" form (Certificates
     *                                             page) — all optional, all ad-hoc to this one PDF
     *                                             render (nothing here is persisted to the DB).
     *                                             Date-shaped keys arrive as YYYY-MM-DD (native
     *                                             <input type=date>) and are reformatted to match
     *                                             this document's usual date style.
     */
    public function certificate(CertificateType $type, Certificate $certificate, Student $student, array $overrides = []): array
    {
        $student->loadMissing(['schoolClass:id,name', 'section:id,name', 'father:id,name', 'mother:id,name', 'branch:id,name', 'additionalDetail', 'udiseDetail', 'documents']);
        $session = AcademicSession::where('is_current', true)->value('name') ?? '';

        $val = fn (string $key, string $default) => trim((string) ($overrides[$key] ?? '')) !== '' ? $overrides[$key] : $default;
        $dateVal = fn (string $key, string $format, ?Carbon $default) => trim((string) ($overrides[$key] ?? '')) !== ''
            ? (($p = $this->tryParseDate($overrides[$key])) ? $p->format($format) : $overrides[$key])
            : ($default ? $default->format($format) : '');

        $recipientName = $val('recipient_name', $student->name);
        $fatherName = $val('father_name', $student->father->name ?? '');
        $motherName = $val('mother_name', $student->mother->name ?? '');
        $className = $val('class', $student->schoolClass->name ?? '');
        $sectionName = $val('section', $student->section->name ?? '');
        $sessionYear = $val('session_year', $session);
        $dob = $student->dob ? Carbon::parse($student->dob) : null;
        $admissionDate = $student->admission_date ? Carbon::parse($student->admission_date) : null;
        $issueDate = $certificate->issue_date ? Carbon::parse($certificate->issue_date) : now();

        // Class Head — the teacher assigned to the student's class (teachers.school_class_id),
        // independent of section. Distinct from TeacherClassAssignment's per-(class, section)
        // "head" role, which this deliberately ignores.
        $classTeacher = $student->school_class_id
            ? Teacher::where('school_class_id', $student->school_class_id)->orderBy('id')->first()
            : null;

        $body = "This is to certify that {$recipientName}"
            .($fatherName ? ', son/daughter of '.$fatherName : '')
            .' is a bonafide student of this school'
            .($className ? ' studying in Class '.$className : '')
            .($sectionName ? ', Section '.$sectionName : '')
            .($sessionYear ? ' during the academic session '.$sessionYear : '')
            .'.';

        return array_merge($this->schoolContext(), [
            'certificate_title' => $type->label ?: 'Certificate',
            'recipient_name' => $recipientName,
            'father_name' => $fatherName,
            'mother_name' => $motherName,
            'admission_id' => $val('admission_id', $student->admission_no),
            'roll_number' => $val('roll_number', $student->roll_no ?? ''),
            'class' => $className,
            'section' => $sectionName,
            'branch' => $val('branch', $student->branch->name ?? ''),
            'session_year' => $sessionYear,
            'certificate_body' => $body,
            'dob' => $dateVal('dob', 'd M Y', $dob),
            'dob_words' => trim((string) ($overrides['dob'] ?? '')) !== ''
                ? (($p = $this->tryParseDate($overrides['dob'])) ? $this->dateInWords($p) : '')
                : ($dob ? $this->dateInWords($dob) : ''),
            'purpose' => $val('purpose', $certificate->reason ?? ''),
            'reference_no' => $certificate->certificate_no,
            'issue_date' => $dateVal('issue_date', 'd M Y', $issueDate),
            'issue_date_numeric' => $dateVal('issue_date', 'd-m-Y', $issueDate),
            'conduct' => $val('conduct', 'good'),
            'character' => $val('character', 'excellent'),
            'photo' => $this->resolveStoredImage($student->documents?->photo_path),
            'nationality' => $val('nationality', $student->nationality ?? ''),
            'category' => $val('category', $student->category ?? ''),
            'admission_date' => $dateVal('admission_date', 'd-m-Y', $admissionDate),
            'admission_date_words' => $dateVal('admission_date', 'd M Y', $admissionDate),
            'dob_numeric' => $dateVal('dob', 'd-m-Y', $dob),
            'pen_no' => $val('pen_no', $student->udiseDetail?->student_pen ?: ($student->additionalDetail?->pen_no ?? '')),
            'aadhar_no' => $val('aadhar_no', $student->aadhar_no ?? ''),
            'class_teacher_name' => $classTeacher->name ?? '',
            'class_teacher_signature_image' => $this->resolveStoredImage($classTeacher?->signature_path),
            'sr_no' => $certificate->certificate_no,
            'remarks' => $val('remarks', $certificate->remarks ?? ''),
            'book_no' => $val('book_no', ''),
            'last_exam_result' => $val('last_exam_result', ''),
            'failed_status' => $val('failed_status', ''),
            'subjects_studied' => $val('subjects_studied', ''),
            'promotion_status' => $val('promotion_status', ''),
            'promoted_class' => $val('promoted_class', ''),
            'working_days' => $val('working_days', ''),
            'presence_days' => $val('presence_days', ''),
            'fee_paid_upto' => $val('fee_paid_upto', ''),
            'fee_concession' => $val('fee_concession', ''),
            'ncc_activities' => $val('ncc_activities', ''),
            'games_activities' => $val('games_activities', ''),
            'general_conduct' => $val('general_conduct', ''),
            'application_date' => $dateVal('application_date', 'd-m-Y', null),
            // ISO (YYYY-MM-DD) mirrors of the date fields above — not used by any PDF template,
            // only so the "Prepare" form's <input type=date> fields can be pre-filled correctly.
            'dob_iso' => $dateVal('dob', 'Y-m-d', $dob),
            'admission_date_iso' => $dateVal('admission_date', 'Y-m-d', $admissionDate),
            'issue_date_iso' => $dateVal('issue_date', 'Y-m-d', $issueDate),
            'application_date_iso' => $dateVal('application_date', 'Y-m-d', null),
        ]);
    }

    private function tryParseDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function idCard(IdCard $idCard): array
    {
        $idCard->loadMissing('holder');
        $holder = $idCard->holder;
        $classSection = '';
        $photo = '';
        if ($idCard->holder_type === 'student' && $holder) {
            $holder->loadMissing(['schoolClass:id,name', 'section:id,name', 'documents']);
            $classSection = trim(($holder->schoolClass->name ?? '').($holder->section ? ' ('.$holder->section->name.')' : ''));
            $photo = $this->resolveStoredImage($holder->documents?->photo_path);
        }

        return array_merge($this->schoolContext(), [
            'card_title' => 'Identity Card',
            'holder_name' => $holder->name ?? '',
            'photo' => $photo,
            'id_number' => $idCard->card_no,
            'role_type' => ucfirst((string) $idCard->holder_type),
            'class_section' => $classSection,
            'blood_group' => $holder->blood_group ?? '',
            'contact_no' => $holder->mobile ?? $holder->phone ?? '',
            'valid_until' => $idCard->valid_until
                ? Carbon::parse($idCard->valid_until)->format('d M Y')
                : 'No expiry',
            'barcode' => $idCard->card_no,
            'signature' => 'Principal',
        ]);
    }

    public function transportCard(Student $student, ?StudentTransport $transport): array
    {
        $student->loadMissing(['schoolClass:id,name', 'section:id,name', 'documents']);
        $classSection = trim(($student->schoolClass->name ?? '').($student->section ? ' ('.$student->section->name.')' : ''));

        return array_merge($this->schoolContext(), [
            'card_title' => 'Transport Card',
            'student_name' => $student->name,
            'photo' => $this->resolveStoredImage($student->documents?->photo_path),
            'admission_id' => $student->admission_no,
            'class_section' => $classSection,
            'route_name' => $transport?->route->name ?? 'Not assigned',
            'pickup_stop' => $transport?->routeStop->stop_name ?? '—',
            'driver_name' => $transport?->route?->vehicle?->driver?->name ?? '—',
            'vehicle_no' => $transport?->route?->vehicle?->vehicle_no ?? '—',
            'valid_until' => AcademicSession::where('is_current', true)->value('name') ?? '',
        ]);
    }

    public function libraryCard(LibraryMember $member): array
    {
        $member->loadMissing('member');
        $holder = $member->member;
        $classSection = '';
        $photo = '';
        if ($member->member_type === 'student' && $holder) {
            $holder->loadMissing(['schoolClass:id,name', 'section:id,name', 'documents']);
            $classSection = trim(($holder->schoolClass->name ?? '').($holder->section ? ' ('.$holder->section->name.')' : ''));
            $photo = $this->resolveStoredImage($holder->documents?->photo_path);
        }

        return array_merge($this->schoolContext(), [
            'card_title' => 'Library Card',
            'member_name' => $holder->name ?? '',
            'photo' => $photo,
            'member_id' => $member->library_card_no,
            'member_type' => ucfirst((string) $member->member_type),
            'class_section' => $classSection,
            'valid_from' => $member->joined_date ? Carbon::parse($member->joined_date)->format('d M Y') : '',
            'valid_until' => AcademicSession::where('is_current', true)->value('name') ?? '',
            'barcode' => $member->library_card_no,
        ]);
    }

    public function salarySlip(SalarySlip $slip): array
    {
        $slip->loadMissing('employee');
        $period = $this->periodLabel($slip->period);

        $earnings = ($slip->earnings && count($slip->earnings))
            ? $slip->earnings
            : [['label' => 'Basic', 'amount' => $slip->basic_salary]];
        $earningsLines = collect($earnings)->map(fn ($r) => sprintf('%-22s %10s', $r['label'], number_format((float) $r['amount'], 2)))->implode("\n");
        $deductionLines = collect($slip->deduction_items ?? [])->map(fn ($r) => sprintf('%-22s %10s', $r['label'], number_format((float) $r['amount'], 2)))->implode("\n");

        $hasAttendance = $slip->days_in_month !== null;

        return array_merge($this->schoolContext(), [
            'slip_title' => 'Salary Slip',
            'slip_no' => $slip->slip_no ?? '',
            'employee_name' => $slip->employee->name ?? '',
            'employee_type' => ucfirst((string) $slip->employee_type),
            'employee_code' => $slip->employee->employee_id ?? '',
            'period' => $period,
            'earnings_table' => "Earnings                 Amount\n".$earningsLines,
            'deductions_table' => "Deductions               Amount\n".($deductionLines ?: 'None'),
            'net_salary' => number_format((float) $slip->net_salary, 2),
            'payment_mode' => $slip->payment_mode ?? '',
            'status' => $slip->status ?? '',
            'remarks' => $slip->remarks ?? '',
            // Attendance-based monthly calculation — blank for older ad-hoc slips that
            // predate this (days_in_month is the marker: null means "not attendance-based").
            'days_in_month' => $hasAttendance ? (string) $slip->days_in_month : '',
            'present' => $hasAttendance ? number_format((float) $slip->present, 2) : '',
            'absent' => $hasAttendance ? number_format((float) $slip->absent, 2) : '',
            'cl' => $hasAttendance ? number_format((float) $slip->cl, 2) : '',
            'total_days' => $hasAttendance ? number_format((float) $slip->total_days, 2) : '',
            'per_day_rate' => $hasAttendance ? number_format((float) $slip->per_day_rate, 2) : '',
            'this_month_salary' => $hasAttendance ? number_format((float) $slip->this_month_salary, 2) : '',
            'advance' => $hasAttendance ? number_format((float) $slip->advance, 2) : '',
        ]);
    }

    /** @param  \App\Models\BookExpense  $expense */
    public function bookExpense($expense): array
    {
        $expense->loadMissing(['student:id,name,admission_no,school_class_id,section_id', 'student.schoolClass:id,name', 'student.section:id,name', 'items']);
        $student = $expense->student;
        $classSection = trim(($student?->schoolClass?->name ?? '').($student?->section ? ' ('.$student->section->name.')' : ''));

        $lines = $expense->items->map(function ($item) {
            $name = $item->title ?? 'Item';
            $amount = (float) ($item->price ?? 0);

            return sprintf('%-22s %10s', $name, number_format($amount, 2));
        })->implode("\n");

        return array_merge($this->schoolContext(), [
            'receipt_title' => 'Book Expense Receipt',
            'expense_no' => $expense->expense_no ?? (string) $expense->id,
            'expense_date' => $expense->date
                ? $expense->date->format('d M Y')
                : ($expense->created_at?->format('d M Y') ?? now()->format('d M Y')),
            'student_name' => $student?->name ?? '',
            'admission_id' => $student?->admission_no ?? '',
            'class_section' => $classSection,
            'items_table' => "Book / Item               Amount\n".($lines ?: '—'),
            'total_amount' => number_format((float) ($expense->total_amount ?? 0), 2),
        ]);
    }

    /** Embed an image as HTML, or empty string when no image — keeps photo/signature boxes stable. */
    private function inlineImageHtml(string $dataUri, string $class = ''): string
    {
        if ($dataUri === '') {
            return '';
        }
        $src = htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8');
        $cls = $class !== '' ? ' class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'"' : '';

        return '<img'.$cls.' src="'.$src.'" alt="">';
    }

    /**
     * Keep admit-card instructions short enough for a single A5 page (max 5 list items / ~420 chars).
     */
    private function compactAdmitInstructions(string $html): string
    {
        if (preg_match_all('/<li\b[^>]*>.*?<\/li>/is', $html, $matches) && count($matches[0]) > 5) {
            $items = array_slice($matches[0], 0, 5);
            $tag = str_contains(mb_strtolower($html), '<ul') ? 'ul' : 'ol';

            return '<'.$tag.'>'.implode('', $items).'</'.$tag.'>';
        }

        $plain = trim(html_entity_decode(strip_tags($html)));
        if (mb_strlen($plain) > 420) {
            return '<p>'.e(mb_substr($plain, 0, 400)).'…</p>';
        }

        return $html;
    }

    public function resolveStoredImage(?string $path): string
    {
        if (! $path) {
            return '';
        }
        if (str_starts_with($path, 'data:') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = str_replace('\\', '/', trim($path));
        $candidates = array_values(array_unique(array_filter([
            $normalized,
            ltrim($normalized, '/'),
            preg_replace('#^storage/#', '', $normalized),
            preg_replace('#^public/#', '', $normalized),
            'public/'.ltrim($normalized, '/'),
        ])));

        foreach (['local', 'public'] as $disk) {
            foreach ($candidates as $candidate) {
                try {
                    if (! Storage::disk($disk)->exists($candidate)) {
                        continue;
                    }
                    $contents = Storage::disk($disk)->get($candidate);
                    $mime = Storage::disk($disk)->mimeType($candidate) ?: 'image/jpeg';

                    return 'data:'.$mime.';base64,'.base64_encode($contents);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        if (is_file($normalized)) {
            $contents = file_get_contents($normalized);
            $mime = mime_content_type($normalized) ?: 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        // Never return a relative path Dompdf cannot load — blank box instead.
        return '';
    }

    private function resolvePublicImage(?string $path): string
    {
        return $this->resolveStoredImage($path);
    }

    private function periodLabel(string $period): string
    {
        $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
        [$year, $month] = array_pad(explode('-', $period), 2, '');

        return ($months[$month] ?? $month).' '.$year;
    }

    private function amountInWords(float $amount): string
    {
        $rounded = (int) round($amount);
        if ($rounded <= 0) {
            return 'Zero Rupees Only';
        }

        return $this->numberToWords($rounded).' Rupees Only';
    }

    private function dateInWords(?Carbon $date): string
    {
        if (! $date) {
            return '';
        }

        $dayWords = [
            1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', 5 => 'Fifth', 6 => 'Sixth', 7 => 'Seventh',
            8 => 'Eighth', 9 => 'Ninth', 10 => 'Tenth', 11 => 'Eleventh', 12 => 'Twelfth', 13 => 'Thirteenth',
            14 => 'Fourteenth', 15 => 'Fifteenth', 16 => 'Sixteenth', 17 => 'Seventeenth', 18 => 'Eighteenth',
            19 => 'Nineteenth', 20 => 'Twentieth', 21 => 'Twenty-First', 22 => 'Twenty-Second', 23 => 'Twenty-Third',
            24 => 'Twenty-Fourth', 25 => 'Twenty-Fifth', 26 => 'Twenty-Sixth', 27 => 'Twenty-Seventh',
            28 => 'Twenty-Eighth', 29 => 'Twenty-Ninth', 30 => 'Thirtieth', 31 => 'Thirty-First',
        ];
        $day = $dayWords[(int) $date->format('j')] ?? $date->format('jS');

        return "{$day} {$date->format('F')} {$this->numberToWords((int) $date->format('Y'))}";
    }

    /** Indian numbering system (crore/lakh/thousand) — shared by amountInWords() and dateInWords(). */
    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $chunk = function (int $n) use (&$chunk, $ones, $tens) {
            if ($n === 0) {
                return '';
            }
            if ($n < 20) {
                return $ones[$n];
            }
            if ($n < 100) {
                return trim($tens[intdiv($n, 10)].($n % 10 ? ' '.$ones[$n % 10] : ''));
            }

            return trim($ones[intdiv($n, 100)].' Hundred'.($n % 100 ? ' '.$chunk($n % 100) : ''));
        };

        $crore = intdiv($number, 10000000);
        $lakh = intdiv($number % 10000000, 100000);
        $thousand = intdiv($number % 100000, 1000);
        $rest = $number % 1000;

        $parts = [];
        if ($crore) {
            $parts[] = $chunk($crore).' Crore';
        }
        if ($lakh) {
            $parts[] = $chunk($lakh).' Lakh';
        }
        if ($thousand) {
            $parts[] = $chunk($thousand).' Thousand';
        }
        if ($rest) {
            $parts[] = $chunk($rest);
        }

        return trim(implode(' ', $parts));
    }
}
