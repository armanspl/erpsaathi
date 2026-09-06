<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ErpFeeStructure;
use App\Models\FeeHead;
use App\Models\FineRule;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class FeeManagementSeeder extends Seeder
{
    public function run(): void
    {
        $heads = [
            ['name' => 'Tuition Fee', 'description' => 'Core academic fee'],
            ['name' => 'Admission Fee', 'description' => 'One-time new admission charge'],
            ['name' => 'Transport Fee', 'description' => 'School bus service'],
            ['name' => 'Library Fee', 'description' => 'Library access and books'],
            ['name' => 'Exam Fee', 'description' => 'Examination and report cards'],
        ];
        foreach ($heads as $head) {
            FeeHead::firstOrCreate(['name' => $head['name']], $head);
        }

        $tuition = FeeHead::where('name', 'Tuition Fee')->first();
        $admission = FeeHead::where('name', 'Admission Fee')->first();
        $exam = FeeHead::where('name', 'Exam Fee')->first();

        $session = AcademicSession::where('is_current', true)->first();
        $class1 = SchoolClass::where('name', '1')->first();

        if ($session && $class1 && $tuition && $admission && $exam) {
            $structure = [
                ['fee_head_id' => $tuition->id, 'amount' => 18000, 'frequency' => 'annual'],
                ['fee_head_id' => $admission->id, 'amount' => 5000, 'frequency' => 'one_time'],
                ['fee_head_id' => $exam->id, 'amount' => 1500, 'frequency' => 'annual'],
            ];
            foreach ($structure as $row) {
                ErpFeeStructure::firstOrCreate(
                    ['academic_session_id' => $session->id, 'school_class_id' => $class1->id, 'fee_head_id' => $row['fee_head_id']],
                    ['amount' => $row['amount'], 'frequency' => $row['frequency']]
                );
            }
        }

        $fineRules = [
            ['name' => 'Late Fee Payment', 'type' => 'per_day', 'amount' => 10, 'grace_days' => 7],
            ['name' => 'Bounced Cheque', 'type' => 'fixed', 'amount' => 500, 'grace_days' => 0],
        ];
        foreach ($fineRules as $rule) {
            FineRule::firstOrCreate(['name' => $rule['name']], $rule);
        }
    }
}
