<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingSupportSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'customer')->first();
        if (! $user) {
            return;
        }

        $now = now();
        $ticketId = DB::table('support_tickets')->insertGetId([
            'user_id' => $user->id,
            'subject' => 'Cannot complete transfer',
            'status' => 'open',
            'ticket_number' => 'TKT-' . str_pad((string) rand(1000, 9999), 6, '0'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('support_ticket_messages')->insert([
            [
                'support_ticket_id' => $ticketId,
                'user_id' => $user->id,
                'is_staff' => false,
                'body' => 'I am unable to complete a transfer to my beneficiary. Error code 500.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'support_ticket_id' => $ticketId,
                'user_id' => null,
                'is_staff' => true,
                'body' => 'Thank you for reaching out. We are looking into this and will get back to you within 24 hours.',
                'created_at' => $now->copy()->addHours(2),
                'updated_at' => $now,
            ],
        ]);
    }
}
