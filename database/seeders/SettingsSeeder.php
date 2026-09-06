<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ErpRole;
use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        SchoolSetting::firstOrCreate(['id' => 1], [
            'school_name' => 'Global Access School',
            'address' => 'Sector 12, Jaipur, Rajasthan',
            'phone' => '9876543210',
            'email' => 'info@globalaccessschool.co.in',
            'website' => 'https://globalaccessschool.co.in',
            'established_year' => 2005,
            'current_branch' => 'Main Campus',
        ]);

        $sessions = [
            ['name' => '2024-2025', 'start_date' => '2024-04-01', 'end_date' => '2025-03-31', 'status' => 'closed', 'is_current' => false],
            ['name' => '2025-2026', 'start_date' => '2025-04-01', 'end_date' => '2026-03-31', 'status' => 'closed', 'is_current' => false],
            ['name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'status' => 'current', 'is_current' => true],
        ];
        foreach ($sessions as $session) {
            AcademicSession::firstOrCreate(['name' => $session['name']], $session);
        }

        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full access to every module', 'permissions' => ['*'], 'is_system' => true],
            ['name' => 'Staff', 'slug' => 'staff', 'description' => 'Standard staff access', 'permissions' => ['dashboard.view', 'students.view', 'attendance.manage'], 'is_system' => true],
            ['name' => 'Accountant', 'slug' => 'accountant', 'description' => 'Fee and finance modules only', 'permissions' => ['fee.manage', 'finance.manage'], 'is_system' => false],
        ];
        foreach ($roles as $role) {
            ErpRole::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
