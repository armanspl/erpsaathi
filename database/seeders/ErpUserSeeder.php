<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ErpUser;

class ErpUserSeeder extends Seeder
{
    public function run(): void
    {
        ErpUser::create([
            'name'     => 'Admin User',
            'email'    => 'admin@globalschool.edu',
            'password' => 'password123',
            'role'     => 'admin',
        ]);

        ErpUser::create([
            'name'     => 'Staff User',
            'email'    => 'staff@globalschool.edu',
            'password' => 'password123',
            'role'     => 'staff',
        ]);

        ErpUser::create([
            'name'     => 'Teacher One',
            'email'    => 'teacher@globalschool.edu',
            'password' => 'password123',
            'role'     => 'staff',
        ]);
    }
}