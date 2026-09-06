<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\ParentGuardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class PeopleSeeder extends Seeder
{
    public function run(): void
    {
        $class1 = SchoolClass::where('name', '1')->first();
        $section1A = Section::where('school_class_id', $class1?->id)->where('name', 'A')->first();

        $father = ParentGuardian::firstOrCreate(['email' => 'ramesh.sharma@example.com'], [
            'name' => 'Ramesh Sharma', 'phone' => '9811100001', 'occupation' => 'Business Owner', 'address' => 'Sector 12, Jaipur',
        ]);
        $mother = ParentGuardian::firstOrCreate(['email' => 'sunita.sharma@example.com'], [
            'name' => 'Sunita Sharma', 'phone' => '9811100002', 'occupation' => 'Homemaker', 'address' => 'Sector 12, Jaipur',
        ]);

        if ($class1) {
            Student::firstOrCreate(['admission_no' => 'ADM-1001'], [
                'name' => 'Aarav Sharma',
                'roll_no' => 1,
                'school_class_id' => $class1->id,
                'section_id' => $section1A?->id,
                'father_id' => $father->id,
                'mother_id' => $mother->id,
                'gender' => 'Male',
                'dob' => '2016-03-14',
                'blood_group' => 'B+',
                'nationality' => 'Indian',
                'mobile' => '9811100001',
                'status' => 'Active',
                'admission_date' => '2022-04-01',
            ]);
        }

        $teachers = [
            ['employee_id' => 'TCH-001', 'name' => 'Anjali Verma', 'phone' => '9822200001', 'email' => 'anjali.verma@school.test', 'status' => 'active'],
            ['employee_id' => 'TCH-002', 'name' => 'Rohit Malhotra', 'phone' => '9822200002', 'email' => 'rohit.malhotra@school.test', 'status' => 'active'],
        ];
        foreach ($teachers as $teacher) {
            Teacher::firstOrCreate(['employee_id' => $teacher['employee_id']], $teacher);
        }

        $staff = [
            ['employee_id' => 'STF-001', 'name' => 'Kavita Joshi', 'department' => 'Administration', 'phone' => '9833300001', 'status' => 'active'],
            ['employee_id' => 'STF-002', 'name' => 'Manoj Kumar', 'department' => 'Accounts', 'phone' => '9833300002', 'status' => 'active'],
        ];
        foreach ($staff as $member) {
            Staff::firstOrCreate(['employee_id' => $member['employee_id']], $member);
        }

        $drivers = [
            ['employee_id' => 'DRV-001', 'name' => 'Mahesh Choudhary', 'phone' => '9844400001', 'license_no' => 'RJ14-2019-0012345', 'vehicle_no' => 'RJ-14-GA-3321', 'status' => 'active'],
        ];
        foreach ($drivers as $driver) {
            Driver::firstOrCreate(['employee_id' => $driver['employee_id']], $driver);
        }
    }
}
