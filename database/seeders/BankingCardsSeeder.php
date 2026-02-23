<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingCardsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = DB::table('accounts')->where('type', 'current')->where('status', 'active')->limit(3)->get();
        $now = now();

        foreach ($accounts as $account) {
            $cardId = DB::table('cards')->insertGetId([
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'last_four' => (string) rand(1000, 9999),
                'expiry_date' => $now->copy()->addYears(3)->format('Y-m-d'),
                'type' => $account->id === $accounts->first()->id ? 'virtual' : 'physical',
                'status' => 'active',
                'pin_set_at' => $now->copy()->subDays(30),
                'daily_limit_amount' => 5000.0000,
                'online_enabled' => true,
                'international_enabled' => $account->id === $accounts->first()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['online', 'international', 'pos', 'atm'] as $cat) {
                DB::table('card_spending_limits')->insert([
                    'card_id' => $cardId,
                    'category' => $cat,
                    'limit_amount' => rand(500, 3000),
                    'enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
