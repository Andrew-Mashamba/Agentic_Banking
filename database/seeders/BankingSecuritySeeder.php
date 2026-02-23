<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingSecuritySeeder extends Seeder
{
    public function run(): void
    {
        $users = User::limit(4)->pluck('id');
        $now = now();

        foreach ($users as $userId) {
            foreach (range(1, 5) as $i) {
                DB::table('login_history')->insert([
                    'user_id' => $userId,
                    'ip_address' => '192.168.1.' . rand(1, 255),
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'logged_at' => $now->copy()->subDays($i * 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('user_transaction_limits')->insert([
                ['user_id' => $userId, 'limit_type' => 'daily', 'amount' => 5000.0000, 'currency' => 'USD', 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => $userId, 'limit_type' => 'per_transaction', 'amount' => 2000.0000, 'currency' => 'USD', 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        $customerId = User::where('role', 'customer')->value('id');
        if ($customerId) {
            DB::table('fraud_reports')->insert([
                'user_id' => $customerId,
                'description' => 'Unauthorized transaction on card ending 4242.',
                'status' => 'under_review',
                'reported_at' => $now->copy()->subDays(1),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
