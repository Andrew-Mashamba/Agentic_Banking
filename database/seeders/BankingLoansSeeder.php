<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingLoansSeeder extends Seeder
{
    public function run(): void
    {
        $account = DB::table('accounts')->where('type', 'current')->where('status', 'active')->first();
        $productId = DB::table('loan_products')->where('name', 'Personal Loan')->value('id');
        if (! $account || ! $productId) {
            return;
        }

        $now = now();
        $loanId = DB::table('loans')->insertGetId([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'loan_product_id' => $productId,
            'amount' => 10000.0000,
            'outstanding_balance' => 7500.0000,
            'interest_rate' => 12.5,
            'status' => 'active',
            'disbursed_at' => $now->copy()->subMonths(6),
            'maturity_date' => $now->copy()->addMonths(18)->format('Y-m-d'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (range(1, 6) as $m) {
            $due = $now->copy()->subMonths(6)->addMonths($m);
            DB::table('loan_repayment_schedules')->insert([
                'loan_id' => $loanId,
                'due_date' => $due->format('Y-m-d'),
                'principal_amount' => 416.67,
                'interest_amount' => 104.17,
                'status' => $m <= 3 ? 'paid' : 'pending',
                'paid_at' => $m <= 3 ? $due->copy()->addDays(2) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $txId = DB::table('transactions')->where('account_id', $account->id)->value('id');
        DB::table('loan_repayments')->insert([
            [
                'loan_id' => $loanId,
                'amount' => 520.84,
                'paid_at' => $now->copy()->subMonths(4),
                'transaction_id' => $txId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('loan_applications')->insert([
            'user_id' => $account->user_id,
            'loan_product_id' => $productId,
            'amount_requested' => 5000.0000,
            'tenor_months' => 12,
            'purpose' => 'Education',
            'status' => 'approved',
            'eligibility_result' => json_encode(['eligible' => true, 'approved_amount' => 5000]),
            'approved_at' => $now->copy()->subDays(5),
            'loan_id' => $loanId,
            'created_at' => $now,
                'updated_at' => $now,
        ]);

        DB::table('early_settlement_requests')->insert([
            'loan_id' => $loanId,
            'requested_at' => $now->copy()->subDays(2),
            'settlement_amount' => 7200.0000,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('loan_topup_requests')->insert([
            'loan_id' => $loanId,
            'amount_requested' => 2000.0000,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
