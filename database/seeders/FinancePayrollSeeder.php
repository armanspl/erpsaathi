<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\SalarySlip;
use App\Models\SalaryStructure;
use App\Models\Staff;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class FinancePayrollSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Utilities', 'Maintenance', 'Stationery', 'Miscellaneous'];
        foreach ($categories as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }
        $utilities = ExpenseCategory::where('name', 'Utilities')->first();
        $maintenance = ExpenseCategory::where('name', 'Maintenance')->first();

        $account = BankAccount::firstOrCreate(
            ['account_number' => '30012345678'],
            ['account_name' => 'Main Operating Account', 'bank_name' => 'State Bank of India', 'ifsc_code' => 'SBIN0001234', 'branch' => 'Jaipur Main', 'opening_balance' => 100000]
        );

        Expense::firstOrCreate(
            ['voucher_no' => 'EXP-2026-0001'],
            ['expense_category_id' => $utilities->id, 'title' => 'Electricity Bill — July', 'amount' => 8500, 'date' => '2026-07-10', 'payment_mode' => 'Bank', 'bank_account_id' => $account->id]
        );
        Expense::firstOrCreate(
            ['voucher_no' => 'EXP-2026-0002'],
            ['expense_category_id' => $maintenance->id, 'title' => 'Plumbing Repair', 'amount' => 1500, 'date' => '2026-07-15', 'payment_mode' => 'Cash']
        );

        Income::firstOrCreate(
            ['voucher_no' => 'INC-2026-0001'],
            ['source' => 'Donation — Alumni Fund', 'amount' => 25000, 'date' => '2026-07-05', 'payment_mode' => 'Cash']
        );

        BankTransaction::firstOrCreate(
            ['bank_account_id' => $account->id, 'reference_no' => 'DEP-0001'],
            ['type' => 'Deposit', 'amount' => 50000, 'date' => '2026-07-01', 'remarks' => 'Opening deposit for the term']
        );

        $teacher = Teacher::where('employee_id', 'TCH-001')->first();
        $staffMember = Staff::where('employee_id', 'STF-001')->first();

        if ($teacher) {
            SalaryStructure::firstOrCreate(
                ['employee_type' => 'teacher', 'employee_id' => $teacher->id],
                ['basic_salary' => 35000, 'allowances' => 5000, 'deductions' => 1800]
            );

            SalarySlip::firstOrCreate(
                ['employee_type' => 'teacher', 'employee_id' => $teacher->id, 'period' => '2026-06'],
                ['basic_salary' => 35000, 'allowances' => 5000, 'deductions' => 1800, 'net_salary' => 38200, 'status' => 'Paid', 'paid_on' => '2026-06-30', 'payment_mode' => 'Bank']
            );
        }

        if ($staffMember) {
            SalaryStructure::firstOrCreate(
                ['employee_type' => 'staff', 'employee_id' => $staffMember->id],
                ['basic_salary' => 22000, 'allowances' => 2000, 'deductions' => 900]
            );
        }
    }
}
