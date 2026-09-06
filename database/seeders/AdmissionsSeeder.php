<?php

namespace Database\Seeders;

use App\Models\AdmissionEnquiry;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class AdmissionsSeeder extends Seeder
{
    public function run(): void
    {
        $class3 = SchoolClass::where('name', '3')->first();
        $classNursery = SchoolClass::where('name', 'Nursery')->first();

        if ($class3) {
            AdmissionEnquiry::firstOrCreate(['enquiry_no' => 'ENQ-26-0001'], [
                'student_name' => 'Ishaan Gupta',
                'parent_name' => 'Vikram Gupta',
                'phone' => '9855500001',
                'email' => 'vikram.gupta@example.com',
                'class_applying_for_id' => $class3->id,
                'gender' => 'Male',
                'source' => 'Walk-in',
                'stage' => 'follow_up',
                'next_follow_up_date' => now()->addDays(3)->toDateString(),
                'remarks' => 'Interested, requested fee structure details.',
            ]);
        }

        if ($classNursery) {
            AdmissionEnquiry::firstOrCreate(['enquiry_no' => 'ENQ-26-0002'], [
                'student_name' => 'Myra Singh',
                'parent_name' => 'Anil Singh',
                'phone' => '9855500002',
                'class_applying_for_id' => $classNursery->id,
                'gender' => 'Female',
                'source' => 'Referral',
                'stage' => 'enquiry',
                'remarks' => 'First enquiry call received.',
            ]);
        }
    }
}
