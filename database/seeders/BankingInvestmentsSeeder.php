<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingInvestmentsSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('users')->where('role', 'customer')->limit(2)->pluck('id');
        $productIds = DB::table('investment_products')->pluck('id');
        $now = now();

        foreach ($users as $userId) {
            foreach ($productIds->take(2) as $productId) {
                DB::table('investments')->insert([
                    'user_id' => $userId,
                    'investment_product_id' => $productId,
                    'amount' => rand(1000, 5000),
                    'units' => rand(10, 100) / 10,
                    'status' => 'active',
                    'opened_at' => $now->copy()->subMonths(rand(1, 6)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($users as $userId) {
            DB::table('securities_holdings')->insert([
                [
                    'user_id' => $userId,
                    'symbol' => 'TBL',
                    'name' => 'Tanzania Breweries',
                    'quantity' => 100.000000,
                    'average_cost' => 12.5000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $userId,
                    'symbol' => 'NMB',
                    'name' => 'NMB Bank',
                    'quantity' => 50.000000,
                    'average_cost' => 8.2000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        $holdingId = DB::table('securities_holdings')->first()?->id;
        if ($holdingId) {
            DB::table('securities_trades')->insert([
                [
                    'user_id' => DB::table('securities_holdings')->where('id', $holdingId)->value('user_id'),
                    'symbol' => 'TBL',
                    'side' => 'buy',
                    'quantity' => 50,
                    'price' => 12.50,
                    'trade_at' => $now->copy()->subDays(30),
                    'reference' => 'TRD-001',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('dividends')->insert([
                [
                    'investable_type' => 'App\\Models\\SecuritiesHolding',
                    'investable_id' => $holdingId,
                    'amount' => 25.0000,
                    'paid_at' => $now->copy()->subMonth()->format('Y-m-d'),
                    'reference' => 'DIV-TBL-001',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        foreach ($users as $userId) {
            $invId = DB::table('investments')->where('user_id', $userId)->value('id');
            if ($invId) {
                DB::table('dividend_reinvestment_settings')->insert([
                    'user_id' => $userId,
                    'investable_type' => 'App\\Models\\Investment',
                    'investable_id' => $invId,
                    'enabled' => (bool) rand(0, 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
