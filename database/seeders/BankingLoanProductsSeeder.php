<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingLoanProductsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('loan_products')->insert([
            [
                'name' => 'Personal Loan',
                'min_amount' => 1000.0000,
                'max_amount' => 50000.0000,
                'interest_rate' => 12.5000,
                'tenor_months' => 24,
                'eligibility_rules' => json_encode(['min_income' => 1000, 'min_tenure_months' => 6]),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Micro Loan',
                'min_amount' => 100.0000,
                'max_amount' => 2000.0000,
                'interest_rate' => 15.0000,
                'tenor_months' => 3,
                'eligibility_rules' => json_encode(['instant' => true]),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
