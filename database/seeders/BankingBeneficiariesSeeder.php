<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingBeneficiariesSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'customer')->get();
        $now = now();

        $templates = [
            ['name' => 'Alice Smith', 'account_number' => '1002003001', 'bank_code' => 'NBOTZ', 'bank_name' => 'National Bank', 'type' => 'same_bank'],
            ['name' => 'Bob Ltd', 'account_number' => '2003004002', 'bank_code' => 'CRDB', 'bank_name' => 'CRDB Bank', 'type' => 'interbank'],
            ['name' => 'M-Pesa', 'mobile_number' => '+255754123456', 'type' => 'mobile_wallet', 'mobile_wallet_provider' => 'M-Pesa'],
        ];

        foreach ($users as $user) {
            foreach (array_slice($templates, 0, rand(2, 3)) as $t) {
                DB::table('beneficiaries')->insert([
                    'user_id' => $user->id,
                    'name' => $t['name'],
                    'account_number' => $t['account_number'] ?? null,
                    'bank_code' => $t['bank_code'] ?? null,
                    'bank_name' => $t['bank_name'] ?? null,
                    'type' => $t['type'],
                    'mobile_wallet_provider' => $t['mobile_wallet_provider'] ?? null,
                    'mobile_number' => $t['mobile_number'] ?? null,
                    'is_verified' => (bool) rand(0, 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
