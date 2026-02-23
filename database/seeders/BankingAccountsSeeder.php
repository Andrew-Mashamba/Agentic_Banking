<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::whereIn('role', ['customer'])->get();
        if ($customers->isEmpty()) {
            return;
        }

        $now = now();
        foreach ($customers as $user) {
            $current = DB::table('accounts')->insertGetId([
                'user_id' => $user->id,
                'type' => 'current',
                'account_number' => 'ACC' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '001',
                'currency' => 'USD',
                'balance' => 15000.0000,
                'nickname' => 'Main Account',
                'is_primary' => true,
                'status' => 'active',
                'opened_at' => $now->copy()->subMonths(12),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('accounts')->insert([
                [
                    'user_id' => $user->id,
                    'type' => 'savings',
                    'account_number' => 'SAV' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '001',
                    'currency' => 'USD',
                    'balance' => 5200.5000,
                    'nickname' => 'Emergency Fund',
                    'is_primary' => false,
                    'status' => 'active',
                    'opened_at' => $now->copy()->subMonths(8),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // Sample transactions for first customer
            if ($user->id === $customers->first()->id) {
                $accountId = DB::table('accounts')->where('user_id', $user->id)->where('type', 'current')->value('id');
                $balance = 15000.00;
                $txs = [
                    ['credit', 500.00, 'Salary credit', 'SAL-2024-001'],
                    ['debit', 120.50, 'Utility payment', 'UTL-001'],
                    ['debit', 45.00, 'Mobile wallet top-up', 'MW-001'],
                    ['credit', 250.00, 'Transfer from savings', 'TRF-002'],
                    ['debit', 89.99, 'Merchant payment', 'QR-001'],
                ];
                foreach ($txs as $i => $t) {
                    $balance += $t[0] === 'credit' ? $t[1] : -$t[1];
                    DB::table('transactions')->insert([
                        'account_id' => $accountId,
                        'type' => $t[0],
                        'amount' => $t[1],
                        'balance_after' => round($balance, 4),
                        'reference' => $t[3],
                        'description' => $t[2],
                        'created_at' => $now->copy()->subDays(10 - $i),
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Set primary_account_id on users (first account per user)
        foreach ($customers as $user) {
            $firstAccountId = DB::table('accounts')->where('user_id', $user->id)->where('is_primary', true)->value('id');
            if ($firstAccountId) {
                DB::table('users')->where('id', $user->id)->update(['primary_account_id' => $firstAccountId]);
            }
        }
    }
}
