<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingTradeSeeder extends Seeder
{
    public function run(): void
    {
        $corpUserId = DB::table('corporate_users')->value('id');
        if (! $corpUserId) {
            return;
        }

        $now = now();

        DB::table('letter_of_credit_applications')->insert([
            'corporate_user_id' => $corpUserId,
            'amount' => 50000.0000,
            'currency' => 'USD',
            'details' => json_encode(['beneficiary' => 'Supplier XYZ', 'goods' => 'Equipment']),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('bank_guarantee_requests')->insert([
            'corporate_user_id' => $corpUserId,
            'amount' => 25000.0000,
            'currency' => 'USD',
            'details' => json_encode(['type' => 'performance', 'contract_ref' => 'CON-2024-001']),
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('forex_bookings')->insert([
            'corporate_user_id' => $corpUserId,
            'from_currency' => 'USD',
            'to_currency' => 'TZS',
            'amount' => 10000.0000,
            'rate' => 2640.5000,
            'status' => 'confirmed',
            'booked_at' => $now->copy()->subDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
