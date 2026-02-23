<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingBranchesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $branchIds = [];
        $branches = [
            ['name' => 'Main Branch', 'address' => '100 Independence Avenue', 'latitude' => -6.792354, 'longitude' => 39.208330, 'phone' => '+255222123456'],
            ['name' => 'Mlimani City', 'address' => 'Mlimani City Mall', 'latitude' => -6.781234, 'longitude' => 39.212345, 'phone' => '+255222234567'],
            ['name' => 'Masaki', 'address' => 'Masaki Peninsula', 'latitude' => -6.745678, 'longitude' => 39.267890, 'phone' => '+255222345678'],
        ];

        foreach ($branches as $b) {
            $branchIds[] = DB::table('branches')->insertGetId([
                'name' => $b['name'],
                'address' => $b['address'],
                'latitude' => $b['latitude'],
                'longitude' => $b['longitude'],
                'phone' => $b['phone'],
                'opening_hours' => json_encode(['mon_fri' => '08:00-17:00', 'sat' => '08:00-13:00']),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($branchIds as $bid) {
            DB::table('atms')->insert([
                'branch_id' => $bid,
                'address' => DB::table('branches')->where('id', $bid)->value('address') . ' (ATM)',
                'latitude' => DB::table('branches')->where('id', $bid)->value('latitude'),
                'longitude' => DB::table('branches')->where('id', $bid)->value('longitude'),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $user = User::where('role', 'customer')->first();
        if ($user && ! empty($branchIds)) {
            DB::table('appointments')->insert([
                'user_id' => $user->id,
                'branch_id' => $branchIds[0],
                'type' => 'branch',
                'scheduled_at' => $now->copy()->addDays(3),
                'status' => 'scheduled',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('faqs')->insert([
            ['category' => 'general', 'question' => 'How do I reset my PIN?', 'answer' => 'Go to Security > Change PIN and follow the steps.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'transfers', 'question' => 'What is the transfer limit?', 'answer' => 'Default daily limit is USD 5,000. You can change it in Settings.', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'cards', 'question' => 'How do I freeze my card?', 'answer' => 'In Cards, select the card and tap Freeze. You can unfreeze anytime.', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
