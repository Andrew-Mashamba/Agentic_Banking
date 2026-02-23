<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingKycSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'customer')->limit(3)->pluck('id');
        $now = now();

        foreach ($users as $userId) {
            $subId = DB::table('kyc_submissions')->insertGetId([
                'user_id' => $userId,
                'status' => rand(0, 2) === 0 ? 'approved' : 'pending',
                'submitted_at' => $now->copy()->subDays(rand(5, 60)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('kyc_documents')->insert([
                [
                    'kyc_submission_id' => $subId,
                    'document_type' => 'national_id',
                    'file_path' => 'kyc/' . $userId . '/id_front.pdf',
                    'verified_at' => $now->copy()->subDays(rand(1, 30)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kyc_submission_id' => $subId,
                    'document_type' => 'proof_of_address',
                    'file_path' => 'kyc/' . $userId . '/utility.pdf',
                    'verified_at' => $now->copy()->subDays(rand(1, 30)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }
}
