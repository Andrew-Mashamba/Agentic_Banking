<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingNotificationPreferencesSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('role', ['customer', 'admin', 'manager'])->pluck('id');
        $now = now();

        foreach ($users as $userId) {
            foreach (['push', 'sms', 'email'] as $channel) {
                DB::table('notification_preferences')->insert([
                    'user_id' => $userId,
                    'channel' => $channel,
                    'transaction_alerts' => true,
                    'low_balance_alerts' => true,
                    'low_balance_threshold' => $channel === 'push' ? 100.0000 : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
