<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\Student;
use Illuminate\Database\Seeder;

class DocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $student = Student::where('admission_no', 'ADM-1001')->first();
        if (! $student) {
            return;
        }

        Certificate::firstOrCreate(
            ['certificate_no' => 'BON-2026-0001'],
            ['student_id' => $student->id, 'type' => 'Bonafide', 'issue_date' => '2026-07-10', 'reason' => 'Passport application']
        );

        IdCard::firstOrCreate(
            ['card_no' => 'IDC-2026-0001'],
            ['holder_type' => 'student', 'holder_id' => $student->id, 'issued_date' => '2026-04-01', 'valid_until' => '2027-03-31', 'status' => 'Active']
        );
    }
}
