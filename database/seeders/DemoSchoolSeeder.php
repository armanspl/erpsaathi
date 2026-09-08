<?php

namespace Database\Seeders;

use App\Models\ErpUser;
use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

/**
 * Sample data for the public Try Demo school.
 * Idempotent — safe to re-run; callers should skip when students already exist.
 */
class DemoSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            AcademicsSeeder::class,
        ]);

        // Extra demo viewers (admin already created by provisioner).
        ErpUser::query()->updateOrCreate(
            ['email' => 'staff@globalschool.edu'],
            ['name' => 'Staff User', 'password' => 'password123', 'role' => 'staff', 'is_active' => true]
        );
        ErpUser::query()->updateOrCreate(
            ['email' => 'teacher@globalschool.edu'],
            ['name' => 'Teacher One', 'password' => 'password123', 'role' => 'staff', 'is_active' => true]
        );

        $this->call([
            PeopleSeeder::class,
            AdmissionsSeeder::class,
            FeeManagementSeeder::class,
            AttendanceSeeder::class,
            ExamSeeder::class,
            FinancePayrollSeeder::class,
            TransportSeeder::class,
            LibrarySeeder::class,
            InventorySeeder::class,
            HostelSeeder::class,
            DocumentsSeeder::class,
            CommunicationSeeder::class,
            MeetingsSeeder::class,
        ]);

        SchoolSetting::query()->update([
            'school_name' => config('tenancy.demo.name', 'Demo School'),
            'email' => config('tenancy.demo.admin_email', 'demo@erpsaathi.com'),
        ]);
    }
}
