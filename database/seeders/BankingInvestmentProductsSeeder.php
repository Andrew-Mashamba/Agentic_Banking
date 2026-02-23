<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingInvestmentProductsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('investment_products')->insert([
            ['type' => 'tbills', 'name' => '91-Day Treasury Bill', 'code' => 'TB91', 'min_amount' => 1000.0000, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'bond', 'name' => 'Government Bond 5Y', 'code' => 'GB5', 'min_amount' => 5000.0000, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'mutual_fund', 'name' => 'Equity Growth Fund', 'code' => 'EGF', 'min_amount' => 500.0000, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
