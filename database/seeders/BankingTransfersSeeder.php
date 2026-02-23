<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingTransfersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = DB::table('accounts')->where('status', 'active')->get();
        $beneficiaries = DB::table('beneficiaries')->pluck('id', 'user_id');
        $now = now();

        if ($accounts->isEmpty()) {
            return;
        }

        $from = $accounts->first();
        $toAccount = $accounts->where('user_id', '!=', $from->user_id)->first();
        $benId = $beneficiaries[$from->user_id] ?? null;

        $toId = $toAccount?->id;
        DB::table('transfers')->insert([
            [
                'from_account_id' => $from->id,
                'to_account_id' => $toId,
                'beneficiary_id' => $benId,
                'amount' => 500.0000,
                'currency' => 'USD',
                'type' => $toAccount ? 'same_bank' : 'mobile_wallet',
                'status' => 'executed',
                'reference' => 'TRF-' . strtoupper(bin2hex(random_bytes(4))),
                'scheduled_at' => null,
                'executed_at' => $now->copy()->subDays(2),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'from_account_id' => $from->id,
                'to_account_id' => null,
                'beneficiary_id' => $benId,
                'amount' => 200.0000,
                'currency' => 'USD',
                'type' => 'utility',
                'status' => 'scheduled',
                'reference' => 'UTL-SCH-001',
                'scheduled_at' => $now->copy()->addDays(3),
                'executed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'from_account_id' => $from->id,
                'to_account_id' => $toId,
                'beneficiary_id' => $benId,
                'amount' => 1500.0000,
                'currency' => 'USD',
                'type' => 'same_bank',
                'status' => 'pending',
                'reference' => 'TRF-PEND-001',
                'scheduled_at' => null,
                'executed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        if ($benId && $from) {
            DB::table('recurring_transfers')->insert([
                'user_id' => $from->user_id,
                'from_account_id' => $from->id,
                'beneficiary_id' => $benId,
                'amount' => 100.0000,
                'frequency' => 'monthly',
                'next_run_at' => $now->copy()->addMonth()->startOfMonth(),
                'end_at' => null,
                'last_run_at' => $now->copy()->subMonth(),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $batchId = DB::table('bulk_payment_batches')->insertGetId([
            'user_id' => $from->user_id,
            'name' => 'January Salary Run',
            'total_amount' => 15000.0000,
            'total_count' => 5,
            'status' => 'completed',
            'processed_at' => $now->copy()->subDays(5),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $transferId = DB::table('transfers')->where('from_account_id', $from->id)->value('id');
        foreach (range(1, 3) as $i) {
            $bid = DB::table('beneficiaries')->where('user_id', $from->user_id)->skip($i - 1)->value('id');
            if ($bid) {
                DB::table('bulk_payment_items')->insert([
                    'bulk_payment_batch_id' => $batchId,
                    'beneficiary_id' => $bid,
                    'amount' => 3000.0000,
                    'status' => 'completed',
                    'transfer_id' => $transferId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $pendingTransferId = DB::table('transfers')->where('status', 'pending')->value('id');
        if ($pendingTransferId) {
            $approverId = DB::table('users')->where('role', 'manager')->value('id');
            if ($approverId) {
                DB::table('transfer_approvals')->insert([
                    'transfer_id' => $pendingTransferId,
                    'approver_user_id' => $approverId,
                    'level' => 1,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
