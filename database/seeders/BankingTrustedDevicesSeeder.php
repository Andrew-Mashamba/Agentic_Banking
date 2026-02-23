<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingTrustedDevicesSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('role', ['customer', 'admin', 'manager'])->limit(4)->pluck('id');
        $now = now();

        foreach ($users as $userId) {
            DB::table('trusted_devices')->insert([
                'user_id' => $userId,
                'device_identifier' => 'device-' . $userId . '-' . bin2hex(random_bytes(8)),
                'name' => 'iPhone / Chrome',
                'last_used_at' => $now->copy()->subHours(rand(1, 48)),
                'trusted_at' => $now->copy()->subDays(rand(7, 90)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
