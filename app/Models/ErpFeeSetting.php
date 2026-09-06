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
            'tally_fee_ledgers' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'default_payment_mode' => 'Cash',
            'receipt_paid_at' => 'SCHOOL',
            'auto_select_current_month' => true,
            'tally_cash_ledger' => 'Cash',
            'tally_bank_ledger' => 'Bank',
            'tally_party_ledger' => 'Fee Receivable',
            'tally_fee_ledgers' => [],
        ]);
    }
}
