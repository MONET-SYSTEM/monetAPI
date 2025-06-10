<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {        // Create default admin account
        Admin::firstOrCreate(
            ['email' => 'admin@monet.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        // You can add more admin accounts here if needed
        Admin::firstOrCreate(
            ['email' => 'superadmin@monet.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('superadmin123'),
                'role' => 'superadmin',
            ]
        );
    }
}
