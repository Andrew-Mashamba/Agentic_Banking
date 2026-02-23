<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BankingUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@agenticbanking.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone_number' => '+255712000001',
                'status' => 'active',
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@agenticbanking.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'phone_number' => '+255712000002',
                'status' => 'active',
            ],
            [
                'name' => 'John Customer',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone_number' => '+255712111001',
                'status' => 'active',
            ],
            [
                'name' => 'Jane Customer',
                'email' => 'jane@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone_number' => '+255712111002',
                'status' => 'active',
            ],
            [
                'name' => 'Corporate User',
                'email' => 'corporate@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone_number' => '+255712111003',
                'status' => 'active',
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                $data
            );
        }
    }
}
