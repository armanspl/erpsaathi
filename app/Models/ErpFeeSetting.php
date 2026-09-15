<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpFeeSetting extends Model
{
    protected $table = 'erp_fee_settings';

    protected $fillable = [
        'default_payment_mode',
        'receipt_paid_at',
        'auto_select_current_month',
        'tc_fee_enabled',
        'tc_fee_amount',
        'tc_allow_pending_fees',
        'tally_company_name',
        'tally_cash_ledger',
        'tally_bank_ledger',
        'tally_party_ledger',
        'tally_fee_ledgers',
    ];

    protected function casts(): array
    {
        return [
            'auto_select_current_month' => 'boolean',
            'tc_fee_enabled' => 'boolean',
            'tc_fee_amount' => 'decimal:2',
            'tc_allow_pending_fees' => 'boolean',
            'tally_fee_ledgers' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'default_payment_mode' => 'Cash',
            'receipt_paid_at' => 'SCHOOL',
            'auto_select_current_month' => true,
            'tc_fee_enabled' => true,
            'tc_fee_amount' => 500,
            'tc_allow_pending_fees' => false,
            'tally_cash_ledger' => 'Cash',
            'tally_bank_ledger' => 'Bank',
            'tally_party_ledger' => 'Fee Receivable',
            'tally_fee_ledgers' => [],
        ]);
    }
}
