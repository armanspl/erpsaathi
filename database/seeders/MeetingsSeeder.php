<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class MeetingsSeeder extends Seeder
{
    public function run(): void
    {
        Meeting::firstOrCreate(
            ['title' => 'Staff Orientation — New Term'],
            [
                'type' => 'Online Meeting', 'description' => 'Kickoff meeting for the new academic term.',
                'meeting_date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '11:00',
                'meeting_link' => 'https://meet.example.com/staff-orientation', 'audience' => 'Staff', 'status' => 'Scheduled',
            ]
        );

        $teacher = Teacher::where('employee_id', 'TCH-001')->first();
        $class = SchoolClass::where('name', '1')->first();
        if ($teacher && $class) {
            Meeting::firstOrCreate(
                ['title' => 'PTM — Class 1'],
                [
                    'type' => 'Parent Teacher Meeting', 'description' => 'Quarterly progress discussion.',
                    'meeting_date' => '2026-07-18', 'start_time' => '15:00', 'end_time' => '17:00',
                    'venue' => 'Classroom 1A', 'audience' => 'Parents', 'teacher_id' => $teacher->id, 'school_class_id' => $class->id,
                    'status' => 'Completed', 'recording_url' => 'https://recordings.example.com/ptm-class1-jul2026',
                ]
            );
        }

        Meeting::firstOrCreate(
            ['title' => 'Admission Open House'],
            [
                'type' => 'Broadcast', 'description' => 'Live broadcast introducing the new admission cycle.',
                'meeting_date' => '2026-08-10', 'start_time' => '17:00', 'end_time' => '18:00',
                'meeting_link' => 'https://meet.example.com/open-house', 'audience' => 'All', 'status' => 'Scheduled',
            ]
        );
    }
}
