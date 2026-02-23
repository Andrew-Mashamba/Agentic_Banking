<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingWalletSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('users')->where('role', 'customer')->pluck('id');
        if ($users->count() < 2) {
            return;
        }

        $now = now();
        $u1 = $users->first();
        $u2 = $users->skip(1)->first();

        DB::table('gift_cards')->insert([
            'purchaser_id' => $u1,
            'recipient_phone' => '+255754999001',
            'recipient_email' => null,
            'amount' => 50.0000,
            'currency' => 'USD',
            'code' => 'GC-' . strtoupper(bin2hex(random_bytes(6))),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('money_requests')->insert([
            [
                'from_user_id' => $u1,
                'to_user_id' => $u2,
                'amount' => 25.0000,
                'status' => 'pending',
                'message' => 'Lunch split',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'from_user_id' => $u2,
                'to_user_id' => $u1,
                'amount' => 100.0000,
                'status' => 'accepted',
                'responded_at' => $now->copy()->subHour(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $splitId = DB::table('bill_splits')->insertGetId([
            'created_by_id' => $u1,
            'total_amount' => 75.0000,
            'description' => 'Office lunch',
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('bill_split_members')->insert([
            ['bill_split_id' => $splitId, 'user_id' => $u1, 'amount_owed' => 37.50, 'created_at' => $now, 'updated_at' => $now],
            ['bill_split_id' => $splitId, 'user_id' => $u2, 'amount_owed' => 37.50, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
