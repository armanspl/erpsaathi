<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed admin user
        $this->call([
            AdminSeeder::class,
        ]);

        // Seed ERP users
        $this->call([
            ErpUserSeeder::class,
        ]);

        // Seed ERP settings defaults
        $this->call([
            SettingsSeeder::class,
        ]);

        // Seed ERP academics defaults
        $this->call([
            AcademicsSeeder::class,
        ]);

        // Seed ERP people defaults
        $this->call([
            PeopleSeeder::class,
        ]);

        // Seed ERP admissions defaults
        $this->call([
            AdmissionsSeeder::class,
        ]);

        // Seed ERP fee management defaults
        $this->call([
            FeeManagementSeeder::class,
        ]);

        // Seed ERP attendance defaults
        $this->call([
            AttendanceSeeder::class,
        ]);

        // Seed ERP exam defaults
        $this->call([
            ExamSeeder::class,
        ]);

        // Seed ERP finance & payroll defaults
        $this->call([
            FinancePayrollSeeder::class,
        ]);

        // Seed ERP transport defaults
        $this->call([
            TransportSeeder::class,
        ]);

        // Seed ERP library defaults
        $this->call([
            LibrarySeeder::class,
        ]);

        // Seed ERP inventory defaults
        $this->call([
            InventorySeeder::class,
        ]);

        // Seed ERP hostel defaults
        $this->call([
            HostelSeeder::class,
        ]);

        // Seed ERP documents defaults
        $this->call([
            DocumentsSeeder::class,
        ]);

        // Seed ERP communication defaults
        $this->call([
            CommunicationSeeder::class,
        ]);

        // Seed ERP meetings defaults
        $this->call([
            MeetingsSeeder::class,
        ]);
    }
}
