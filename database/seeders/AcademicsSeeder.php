<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class AcademicsSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['name' => 'Main Campus', 'principal' => 'Dr. Anjali Verma', 'phone' => '9876543210', 'address' => 'Sector 12, Jaipur', 'status' => 'active'],
            ['name' => 'North Campus', 'principal' => 'Mr. Rakesh Sharma', 'phone' => '9876500011', 'address' => 'Vaishali Nagar, Jaipur', 'status' => 'active'],
            ['name' => 'City Center Branch', 'principal' => 'Ms. Priya Nair', 'phone' => '9876500022', 'address' => 'MI Road, Jaipur', 'status' => 'inactive'],
        ];
        foreach ($branches as $branch) {
            Branch::firstOrCreate(['name' => $branch['name']], $branch);
        }

        $classes = [
            ['name' => 'Nursery', 'capacity' => 30, 'sort_order' => 1],
            ['name' => 'LKG', 'capacity' => 30, 'sort_order' => 2],
            ['name' => 'UKG', 'capacity' => 30, 'sort_order' => 3],
        ];
        foreach (range(1, 10) as $i) {
            $classes[] = ['name' => (string) $i, 'capacity' => 40, 'sort_order' => 3 + $i];
        }
        foreach ($classes as $class) {
            $schoolClass = SchoolClass::firstOrCreate(['name' => $class['name']], $class);

            foreach (['A', 'B'] as $sectionName) {
                Section::firstOrCreate(
                    ['school_class_id' => $schoolClass->id, 'name' => $sectionName],
                    ['capacity' => $class['capacity']]
                );
            }
        }

        $subjects = [
            ['name' => 'English', 'code' => 'ENG'],
            ['name' => 'Mathematics', 'code' => 'MATH'],
            ['name' => 'Science', 'code' => 'SCI'],
            ['name' => 'Social Studies', 'code' => 'SST'],
            ['name' => 'Hindi', 'code' => 'HIN'],
            ['name' => 'Computer Science', 'code' => 'CS'],
        ];
        foreach ($subjects as $subject) {
            Subject::firstOrCreate(['code' => $subject['code']], $subject);
        }

        // Assign the core subjects to every class 1-10 (skip Nursery/LKG/UKG).
        $coreSubjectIds = Subject::whereIn('code', ['ENG', 'MATH', 'SCI', 'SST', 'HIN'])->pluck('id');
        SchoolClass::whereIn('name', array_map('strval', range(1, 10)))->get()->each(function (SchoolClass $class) use ($coreSubjectIds) {
            $class->subjects()->syncWithoutDetaching($coreSubjectIds);
        });
    }
}
