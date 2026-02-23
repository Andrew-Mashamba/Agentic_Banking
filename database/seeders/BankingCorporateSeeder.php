<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankingCorporateSeeder extends Seeder
{
    public function run(): void
    {
        $primaryUser = User::where('email', 'corporate@example.com')->first();
        if (! $primaryUser) {
            return;
        }

        $now = now();
        $corpId = DB::table('corporate_accounts')->insertGetId([
            'primary_user_id' => $primaryUser->id,
            'company_name' => 'Acme Holdings Ltd',
            'registration_number' => 'REG-2020-12345',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subUserId = User::where('email', 'john@example.com')->value('id');
        if ($subUserId) {
            DB::table('corporate_users')->insert([
                'corporate_account_id' => $corpId,
                'user_id' => $subUserId,
                'role' => 'approver',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $workflowId = DB::table('approval_workflows')->insertGetId([
            'corporate_account_id' => $corpId,
            'name' => 'Transfer approval',
            'type' => 'transfer',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('approval_workflow_steps')->insert([
            [
                'approval_workflow_id' => $workflowId,
                'step_order' => 1,
                'role_required' => 'approver',
                'min_amount' => 1000,
                'max_amount' => 50000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $corpUserId = DB::table('corporate_users')->where('corporate_account_id', $corpId)->value('id');
        if ($corpUserId) {
            DB::table('user_approval_rights')->insert([
                'corporate_user_id' => $corpUserId,
                'approval_workflow_id' => $workflowId,
                'max_approvable_amount' => 25000,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
