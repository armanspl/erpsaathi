<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ErpFeeSetting;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\SchoolSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeSettingController extends Controller
{
    public function show()
    {
        $settings = ErpFeeSetting::current();
        $heads = FeeHead::query()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'preferences' => [
                'default_payment_mode' => $settings->default_payment_mode,
                'receipt_paid_at' => $settings->receipt_paid_at,
                'auto_select_current_month' => (bool) $settings->auto_select_current_month,
            ],
            'tally' => [
                'company_name' => $settings->tally_company_name ?: SchoolSetting::current()->school_name,
                'cash_ledger' => $settings->tally_cash_ledger,
                'bank_ledger' => $settings->tally_bank_ledger,
                'party_ledger' => $settings->tally_party_ledger,
                'fee_ledgers' => $settings->tally_fee_ledgers ?: [],
            ],
            'fee_heads' => $heads,
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'default_payment_mode' => ['required', Rule::in(['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'])],
            'receipt_paid_at' => 'required|string|max:100',
            'auto_select_current_month' => 'required|boolean',
        ]);

        $settings = ErpFeeSetting::current();
        $settings->update($data);

        return response()->json(['success' => true, 'preferences' => [
            'default_payment_mode' => $settings->default_payment_mode,
            'receipt_paid_at' => $settings->receipt_paid_at,
            'auto_select_current_month' => (bool) $settings->auto_select_current_month,
        ]]);
    }

    public function updateTally(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'cash_ledger' => 'required|string|max:255',
            'bank_ledger' => 'required|string|max:255',
            'party_ledger' => 'required|string|max:255',
            'fee_ledgers' => 'nullable|array',
            'fee_ledgers.*' => 'nullable|string|max:255',
        ]);

        $settings = ErpFeeSetting::current();
        $settings->update([
            'tally_company_name' => $data['company_name'] ?? null,
            'tally_cash_ledger' => $data['cash_ledger'],
            'tally_bank_ledger' => $data['bank_ledger'],
            'tally_party_ledger' => $data['party_ledger'],
            'tally_fee_ledgers' => $data['fee_ledgers'] ?? [],
        ]);

        return response()->json(['success' => true]);
    }

    /** Preview vouchers that would be exported for Tally. */
    public function tallyPreview(Request $request)
    {
        $vouchers = $this->buildVouchers($request);

        return response()->json([
            'vouchers' => $vouchers,
            'summary' => $this->voucherSummary($vouchers),
        ]);
    }

    public function tallyExport(Request $request): StreamedResponse
    {
        $format = $request->validate(['format' => 'nullable|in:csv,xml'])['format'] ?? 'csv';
        $vouchers = $this->buildVouchers($request);
        $filename = 'tally-fee-vouchers-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'xml') {
            $xml = $this->toTallyXml($vouchers);

            return response()->streamDownload(function () use ($xml) {
                echo $xml;
            }, $filename, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($vouchers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Voucher Type', 'Voucher No', 'Ledger', 'Dr', 'Cr', 'Narration']);
            foreach ($vouchers as $v) {
                foreach ($v['entries'] as $entry) {
                    fputcsv($out, [
                        $v['date'],
                        $v['voucher_type'],
                        $v['voucher_no'],
                        $entry['ledger'],
                        $entry['dr'] ?: '',
                        $entry['cr'] ?: '',
                        $v['narration'],
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<array<string, mixed>> */
    private function buildVouchers(Request $request): array
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $settings = ErpFeeSetting::current();
        $ledgerMap = $settings->tally_fee_ledgers ?: [];

        $query = FeePayment::with([
            'student:id,name,admission_no,branch_id',
            'student.branch:id,name',
        ])->orderBy('payment_date')->orderBy('id');

        if (! AcademicSession::requestWantsAll($request)) {
            $session = AcademicSession::fromRequest($request);
            if ($session) {
                $query->where('academic_session_id', $session->id);
            }
        }

        if (! empty($data['from'])) {
            $query->whereDate('payment_date', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->whereDate('payment_date', '<=', $data['to']);
        }
        if (! empty($data['branch_id'])) {
            $query->whereHas('student', fn ($q) => $q->forBranch((int) $data['branch_id']));
        }

        $vouchers = [];
        foreach ($query->get() as $payment) {
            $net = round((float) $payment->amount - (float) $payment->refunded_amount, 2);
            if ($net <= 0) {
                continue;
            }

            $mode = strtolower((string) $payment->payment_mode);
            $receiptLedger = in_array($mode, ['cash'], true)
                ? $settings->tally_cash_ledger
                : $settings->tally_bank_ledger;

            $entries = [
                ['ledger' => $receiptLedger, 'dr' => $net, 'cr' => 0],
            ];

            $items = is_array($payment->items) ? $payment->items : [];
            $allocated = 0.0;
            foreach ($items as $item) {
                $amt = (float) ($item['amount'] ?? 0);
                if ($amt <= 0) {
                    continue;
                }
                $headId = (string) ($item['fee_head_id'] ?? '');
                $ledger = trim((string) ($ledgerMap[$headId] ?? '')) ?: ($item['fee_head_name'] ?? 'Fee Income');
                $entries[] = ['ledger' => $ledger, 'dr' => 0, 'cr' => $amt];
                $allocated += $amt;
            }

            $remainder = round($net - $allocated, 2);
            if (abs($remainder) >= 0.01) {
                $entries[] = [
                    'ledger' => $settings->tally_party_ledger ?: 'Fee Income',
                    'dr' => $remainder < 0 ? abs($remainder) : 0,
                    'cr' => $remainder > 0 ? $remainder : 0,
                ];
            }

            $vouchers[] = [
                'date' => optional($payment->payment_date)->format('Y-m-d') ?? substr((string) $payment->payment_date, 0, 10),
                'voucher_type' => 'Receipt',
                'voucher_no' => $payment->receipt_no,
                'narration' => trim(sprintf(
                    'Fee receipt %s — %s (%s)%s',
                    $payment->receipt_no,
                    $payment->student?->name ?? 'Student',
                    $payment->student?->admission_no ?? '—',
                    $payment->remarks ? ' · '.$payment->remarks : ''
                )),
                'amount' => $net,
                'entries' => $entries,
            ];
        }

        return $vouchers;
    }

    /** @param  list<array<string, mixed>>  $vouchers */
    private function voucherSummary(array $vouchers): array
    {
        return [
            'vouchers' => count($vouchers),
            'amount' => round(array_sum(array_column($vouchers, 'amount')), 2),
        ];
    }

    /** @param  list<array<string, mixed>>  $vouchers */
    private function toTallyXml(array $vouchers): string
    {
        $company = htmlspecialchars(
            ErpFeeSetting::current()->tally_company_name ?: (SchoolSetting::current()->school_name ?: 'School'),
            ENT_XML1
        );

        $body = '';
        foreach ($vouchers as $v) {
            $date = Carbon::parse($v['date'])->format('Ymd');
            $voucherNo = htmlspecialchars((string) $v['voucher_no'], ENT_XML1);
            $narration = htmlspecialchars((string) $v['narration'], ENT_XML1);
            $entriesXml = '';
            foreach ($v['entries'] as $entry) {
                $ledger = htmlspecialchars((string) $entry['ledger'], ENT_XML1);
                $isDeemed = ((float) $entry['dr']) > 0 ? 'Yes' : 'No';
                $amount = ((float) $entry['dr']) > 0 ? (float) $entry['dr'] : -1 * (float) $entry['cr'];
                $entriesXml .= <<<XML
      <ALLLEDGERENTRIES.LIST>
       <LEDGERNAME>{$ledger}</LEDGERNAME>
       <ISDEEMEDPOSITIVE>{$isDeemed}</ISDEEMEDPOSITIVE>
       <AMOUNT>{$amount}</AMOUNT>
      </ALLLEDGERENTRIES.LIST>

XML;
            }

            $body .= <<<XML
  <VOUCHER VCHTYPE="Receipt" ACTION="Create">
   <DATE>{$date}</DATE>
   <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>
   <VOUCHERNUMBER>{$voucherNo}</VOUCHERNUMBER>
   <NARRATION>{$narration}</NARRATION>
{$entriesXml}  </VOUCHER>

XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
 <HEADER>
  <TALLYREQUEST>Import Data</TALLYREQUEST>
 </HEADER>
 <BODY>
  <IMPORTDATA>
   <REQUESTDESC>
    <REPORTNAME>Vouchers</REPORTNAME>
    <STATICVARIABLES>
     <SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>
    </STATICVARIABLES>
   </REQUESTDESC>
   <REQUESTDATA>
{$body}   </REQUESTDATA>
  </IMPORTDATA>
 </BODY>
</ENVELOPE>
XML;
    }
}
