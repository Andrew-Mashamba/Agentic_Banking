<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingCardlessWithdrawalsSeeder extends Seeder
{
    public function run(): void
    {
        $account = DB::table('accounts')->where('type', 'current')->where('status', 'active')->first();
        if (! $account) {
            return;
        }

        $user = DB::table('users')->where('id', $account->user_id)->first();
        $now = now();

        DB::table('cardless_withdrawals')->insert([
            [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'amount' => 200.0000,
                'code' => str_pad((string) rand(100000, 999999), 6, '0'),
                'expires_at' => $now->copy()->addHours(2),
                'status' => 'pending',
                'used_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'amount' => 100.0000,
                'code' => str_pad((string) rand(100000, 999999), 6, '0'),
                'expires_at' => $now->copy()->subHours(1),
                'status' => 'used',
                'used_at' => $now->copy()->subHours(1),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
