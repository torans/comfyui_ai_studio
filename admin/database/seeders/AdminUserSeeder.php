<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@bkm'],
            [
                'name' => 'System Admin',
                'password' => 'password',
                'role' => 'admin',
                'status' => 'active',
            ],
        );
    }
}
