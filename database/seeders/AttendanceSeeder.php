<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\Student;
use App\Models\WorkingDayConfig;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        WorkingDayConfig::current();

        $holidays = [
            ['name' => 'Independence Day', 'date' => '2026-08-15', 'type' => 'National'],
            ['name' => 'Gandhi Jayanti', 'date' => '2026-10-02', 'type' => 'National'],
            ['name' => 'Diwali', 'date' => '2026-11-08', 'type' => 'Festival'],
        ];
        foreach ($holidays as $holiday) {
            Holiday::firstOrCreate(['date' => $holiday['date']], $holiday);
        }

        $student = Student::where('admission_no', 'ADM-1001')->first();
        if ($student) {
            $student->attendances()->firstOrCreate(['date' => now()->toDateString()], ['status' => 'Present']);
        }
    }
}
