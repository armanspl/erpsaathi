<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\ImportExport\ExportController;
use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\FeePayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Month-wise Fee Collection Report.
 *
 * Distinguishes:
 * - Payment Month  = calendar month of fee_payments.payment_date (when cash was received)
 * - Fee Month(s)   = months listed on fee_payments.items[*].months / .month (dues cleared)
 */
class MonthWiseFeeCollectionController extends Controller
{
    public function meta(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        $months = $session
            ? collect($session->months())->map(fn ($m) => [
                'key' => $m['key'] ?? null,
                'label' => $m['label'] ?? ($m['key'] ?? ''),
            ])->filter(fn ($m) => ! empty($m['key']))->values()
            : collect();

        return response()->json([
            'session' => $session?->only(['id', 'name']),
            'months' => $months,
            'fee_heads' => FeeHead::query()->orderBy('name')->get(['id', 'name']),
            'payment_modes' => ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'],
        ]);
    }

    public function index(Request $request)
    {
        $built = $this->buildReport($request);

        return response()->json([
            'rows' => $built['rows']->values(),
            'summaries' => $built['summaries']->values(),
            'totals' => $built['totals'],
        ]);
    }

    /**
     * Same layout as the Global Workbook's INCOME sheet (one row per student per payment date,
     * a column per fee head, GRAND TOTAL, RECEIPT, PMNT MODE), limited to this report's filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $format = $request->validate([
            'format' => 'nullable|in:xlsx,csv',
        ])['format'] ?? 'xlsx';
        [$data, $sessionFilterId] = $this->filters($request);

        return app(ExportController::class)->incomeSheetDownload($request, [
            'session' => $sessionFilterId ? AcademicSession::find($sessionFilterId) : null,
            'payments' => fn ($query) => $this->applyPaymentFilters($query, $data),
            'fee_month' => $data['fee_month'] ?? null,
        ], 'month-wise-fee-collection', $format);
    }

    /** @return array{0: array<string, mixed>, 1: ?int} validated filters and the session to report on */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'academic_session_id' => 'nullable|integer|exists:academic_sessions,id',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'section_id' => 'nullable|integer|exists:sections,id',
            'payment_month' => 'nullable|string|max:7',
            'fee_month' => 'nullable|string|max:7',
            'payment_mode' => 'nullable|string|max:30',
            'payment_from' => 'nullable|date',
            'payment_to' => 'nullable|date|after_or_equal:payment_from',
            'search' => 'nullable|string|max:100',
        ]);

        $sessionFilterId = null;
        if (! empty($data['academic_session_id'])) {
            $sessionFilterId = (int) $data['academic_session_id'];
        } elseif (! AcademicSession::requestWantsAll($request)) {
            $sessionFilterId = AcademicSession::fromRequest($request, true)?->id;
        }

        return [$data, $sessionFilterId];
    }

    /** Payment date / month / mode and student (class, section, search) filters. */
    private function applyPaymentFilters($query, array $data)
    {
        if (! empty($data['payment_from'])) {
            $query->whereDate('payment_date', '>=', $data['payment_from']);
        }
        if (! empty($data['payment_to'])) {
            $query->whereDate('payment_date', '<=', $data['payment_to']);
        }
        if (! empty($data['payment_month']) && preg_match('/^(\d{4})-(\d{2})$/', $data['payment_month'], $m)) {
            $query->whereYear('payment_date', (int) $m[1])->whereMonth('payment_date', (int) $m[2]);
        }
        if (! empty($data['payment_mode'])) {
            $query->where('payment_mode', $data['payment_mode']);
        }

        if (! empty($data['school_class_id']) || ! empty($data['section_id']) || ! empty($data['search'])) {
            $query->whereHas('student', function ($q) use ($data) {
                if (! empty($data['school_class_id'])) {
                    $q->where('school_class_id', $data['school_class_id']);
                }
                if (! empty($data['section_id'])) {
                    $q->where('section_id', $data['section_id']);
                }
                if (! empty($data['search'])) {
                    $term = '%'.trim($data['search']).'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('name', 'like', $term)
                            ->orWhere('admission_no', 'like', $term);
                    });
                }
            });
        }

        return $query;
    }

    /**
     * @return array{
     *   rows: Collection<int, array<string, mixed>>,
     *   summaries: Collection<int, array<string, mixed>>,
     *   totals: array<string, mixed>
     * }
     */
    private function buildReport(Request $request): array
    {
        [$data, $sessionFilterId] = $this->filters($request);

        $query = FeePayment::query()
            ->with([
                'student:id,name,admission_no,school_class_id,section_id',
                'student.schoolClass:id,name',
                'student.section:id,name',
            ])
            ->whereNotIn('status', ['Rolled Back']);

        if ($sessionFilterId) {
            $query->touchingSessions([$sessionFilterId]);
        }
        $this->applyPaymentFilters($query, $data);

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')->get();
        if ($sessionFilterId) {
            // Multi-session receipts: only the items that belong to the selected session.
            $payments = FeePayment::slicesFor($payments, [$sessionFilterId]);
        }

        $feeMonthFilter = $data['fee_month'] ?? null;
        $rows = collect();

        foreach ($payments as $payment) {
            $netFactor = $this->refundFactor($payment);
            $paymentDate = $payment->payment_date
                ? Carbon::parse($payment->payment_date)->toDateString()
                : null;
            if (! $paymentDate) {
                continue;
            }
            $paymentMonth = substr($paymentDate, 0, 7);
            $student = $payment->student;
            $items = is_array($payment->items) ? $payment->items : [];
            if ($items === []) {
                $net = round(((float) $payment->amount - (float) ($payment->refunded_amount ?? 0)), 2);
                if ($net > 0 && (! $feeMonthFilter || $feeMonthFilter === $paymentMonth)) {
                    $rows->push([
                        'receipt_no' => $payment->receipt_no,
                        'payment_id' => $payment->id,
                        'admission_no' => $student?->admission_no ?? '',
                        'student_name' => $student?->name ?? '',
                        'school_class' => $student?->schoolClass?->name ?? '',
                        'section' => $student?->section?->name ?? '',
                        'payment_date' => $paymentDate,
                        'payment_month' => $paymentMonth,
                        'payment_month_label' => $this->monthLabel($paymentMonth),
                        'fee_month' => $paymentMonth,
                        'fee_month_label' => $this->monthLabel($paymentMonth),
                        'fee_head_id' => null,
                        'fee_head_name' => 'Fee',
                        'amount' => $net,
                        'payment_mode' => $payment->payment_mode,
                    ]);
                }

                continue;
            }

            foreach ($items as $item) {
                $feeMonths = $this->itemFeeMonths($item, $paymentMonth);
                if ($feeMonthFilter) {
                    $feeMonths = array_values(array_filter($feeMonths, fn ($m) => $m === $feeMonthFilter));
                    if ($feeMonths === []) {
                        continue;
                    }
                }

                $lineAmount = round(((float) ($item['amount'] ?? 0)) * $netFactor, 2);
                if ($lineAmount == 0.0 && empty($item['amount'])) {
                    continue;
                }

                $headName = trim((string) ($item['fee_head_name'] ?? 'Fee'));
                $headId = isset($item['fee_head_id']) ? (int) $item['fee_head_id'] : null;
                $perMonth = count($feeMonths) > 0
                    ? round($lineAmount / count($feeMonths), 2)
                    : $lineAmount;

                // Distribute remainder on last month so sum matches line amount.
                $allocated = 0.0;
                $lastIndex = count($feeMonths) - 1;
                foreach ($feeMonths as $i => $feeMonth) {
                    if ($i === $lastIndex) {
                        $amount = round($lineAmount - $allocated, 2);
                    } else {
                        $amount = $perMonth;
                        $allocated += $perMonth;
                    }

                    $rows->push([
                        'receipt_no' => $payment->receipt_no,
                        'payment_id' => $payment->id,
                        'admission_no' => $student?->admission_no ?? '',
                        'student_name' => $student?->name ?? '',
                        'school_class' => $student?->schoolClass?->name ?? '',
                        'section' => $student?->section?->name ?? '',
                        'payment_date' => $paymentDate,
                        'payment_month' => $paymentMonth,
                        'payment_month_label' => $this->monthLabel($paymentMonth),
                        'fee_month' => $feeMonth,
                        'fee_month_label' => $this->monthLabel($feeMonth),
                        'fee_head_id' => $headId,
                        'fee_head_name' => $headName,
                        'amount' => $amount,
                        'payment_mode' => $payment->payment_mode,
                    ]);
                }
            }
        }

        $summaries = $rows
            ->groupBy('payment_month')
            ->map(function (Collection $group, string $paymentMonth) {
                return [
                    'payment_month' => $paymentMonth,
                    'payment_month_label' => $this->monthLabel($paymentMonth),
                    'students' => $group->pluck('admission_no')->unique()->filter()->count(),
                    'receipts' => $group->pluck('payment_id')->unique()->count(),
                    'line_items' => $group->count(),
                    'total_collected' => round($group->sum('amount'), 2),
                ];
            })
            ->sortBy('payment_month')
            ->values();

        $totals = [
            'students' => $rows->pluck('admission_no')->unique()->filter()->count(),
            'receipts' => $rows->pluck('payment_id')->unique()->count(),
            'line_items' => $rows->count(),
            'total_collected' => round($rows->sum('amount'), 2),
        ];

        return [
            'rows' => $rows->values(),
            'summaries' => $summaries,
            'totals' => $totals,
        ];
    }

    private function refundFactor(FeePayment $payment): float
    {
        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return 0.0;
        }
        $refunded = (float) ($payment->refunded_amount ?? 0);
        $net = max(0, $amount - $refunded);

        return $net / $amount;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function itemFeeMonths(array $item, string $fallbackPaymentMonth): array
    {
        $months = [];
        if (! empty($item['months']) && is_array($item['months'])) {
            foreach ($item['months'] as $m) {
                $key = $this->normalizeMonthKey($m);
                if ($key) {
                    $months[] = $key;
                }
            }
        }
        if ($months === [] && ! empty($item['month'])) {
            $key = $this->normalizeMonthKey($item['month']);
            if ($key) {
                $months[] = $key;
            }
        }
        if ($months === []) {
            $months[] = $fallbackPaymentMonth;
        }

        return array_values(array_unique($months));
    }

    private function normalizeMonthKey(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return $raw;
        }
        try {
            return Carbon::parse($raw)->format('Y-m');
        } catch (\Throwable) {
            return null;
        }
    }

    private function monthLabel(string $ym): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $ym)->format('M Y');
        } catch (\Throwable) {
            return $ym;
        }
    }
}
