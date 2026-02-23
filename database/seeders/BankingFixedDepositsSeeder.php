<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingFixedDepositsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = DB::table('accounts')->where('status', 'active')->limit(2)->get();
        $now = now();

        foreach ($accounts as $account) {
            DB::table('fixed_deposits')->insert([
                'account_id' => $account->id,
                'amount' => 5000.0000,
                'tenor_months' => 12,
                'interest_rate' => 8.5000,
                'maturity_date' => $now->copy()->addMonths(10)->format('Y-m-d'),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
