<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingServiceRequestsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'customer')->first();
        if (! $user) {
            return;
        }

        $now = now();
        $types = [
            'cheque_book' => ['leaves' => 50],
            'stop_cheque' => ['cheque_number' => '000123'],
            'account_opening' => ['product' => 'savings'],
            'address_update' => ['new_address' => '456 New Street'],
            'dormant_reactivation' => ['account_id' => 1],
        ];

        foreach ($types as $type => $details) {
            DB::table('service_requests')->insert([
                'user_id' => $user->id,
                'type' => $type,
                'status' => rand(0, 2) === 0 ? 'completed' : 'pending',
                'details' => json_encode($details),
                'resolved_at' => rand(0, 2) === 0 ? $now->copy()->subDays(rand(1, 10)) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
