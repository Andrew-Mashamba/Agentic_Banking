<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F13.4, F14: Corporate accounts, sub-users, approval workflows, approval rights */
class CorporateController extends BaseApiController
{
    public function accounts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $asPrimary = DB::table('corporate_accounts')->where('primary_user_id', $userId)->get();
        $asMember = DB::table('corporate_users')
            ->where('user_id', $userId)
            ->join('corporate_accounts', 'corporate_accounts.id', '=', 'corporate_users.corporate_account_id')
            ->select('corporate_accounts.*', 'corporate_users.role as my_role')
            ->get();

        return $this->success(['owned' => $asPrimary, 'member' => $asMember]);
    }

    public function subUsers(Request $request, int $corporateAccountId): JsonResponse
    {
        $canManage = DB::table('corporate_accounts')->where('id', $corporateAccountId)->where('primary_user_id', $request->user()->id)->exists()
            || DB::table('corporate_users')->where('corporate_account_id', $corporateAccountId)->where('user_id', $request->user()->id)->whereIn('role', ['admin', 'manager'])->exists();
        if (! $canManage) {
            return $this->error('Not authorized', 403);
        }

        $list = DB::table('corporate_users')
            ->where('corporate_account_id', $corporateAccountId)
            ->join('users', 'users.id', '=', 'corporate_users.user_id')
            ->select('corporate_users.id', 'corporate_users.user_id', 'corporate_users.role', 'corporate_users.status', 'users.name', 'users.email')
            ->get();

        return $this->success($list);
    }

    public function addSubUser(Request $request, int $corporateAccountId): JsonResponse
    {
        $isPrimary = DB::table('corporate_accounts')->where('id', $corporateAccountId)->where('primary_user_id', $request->user()->id)->exists();
        if (! $isPrimary) {
            return $this->error('Only primary account holder can add sub-users', 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|max:64',
        ]);

        $exists = DB::table('corporate_users')->where('corporate_account_id', $corporateAccountId)->where('user_id', $validated['user_id'])->exists();
        if ($exists) {
            return $this->error('User already in this corporate account', 422);
        }

        $id = DB::table('corporate_users')->insertGetId([
            'corporate_account_id' => $corporateAccountId,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('corporate_users')->find($id), 'Sub-user added', 201);
    }

    public function approvalWorkflows(Request $request, int $corporateAccountId): JsonResponse
    {
        $canView = DB::table('corporate_accounts')->where('id', $corporateAccountId)->where('primary_user_id', $request->user()->id)->exists()
            || DB::table('corporate_users')->where('corporate_account_id', $corporateAccountId)->where('user_id', $request->user()->id)->exists();
        if (! $canView) {
            return $this->error('Not authorized', 403);
        }

        $list = DB::table('approval_workflows')->where('corporate_account_id', $corporateAccountId)->get();
        return $this->success($list);
    }

    public function userApprovalRights(Request $request, int $corporateAccountId): JsonResponse
    {
        $canView = DB::table('corporate_accounts')->where('id', $corporateAccountId)->where('primary_user_id', $request->user()->id)->exists()
            || DB::table('corporate_users')->where('corporate_account_id', $corporateAccountId)->where('user_id', $request->user()->id)->exists();
        if (! $canView) {
            return $this->error('Not authorized', 403);
        }

        $list = DB::table('user_approval_rights')
            ->join('corporate_users', 'corporate_users.id', '=', 'user_approval_rights.corporate_user_id')
            ->join('approval_workflows', 'approval_workflows.id', '=', 'user_approval_rights.approval_workflow_id')
            ->where('corporate_users.corporate_account_id', $corporateAccountId)
            ->select('user_approval_rights.*', 'approval_workflows.name as workflow_name')
            ->get();

        return $this->success($list);
    }

    public function assignApprovalRight(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'corporate_account_id' => 'required|exists:corporate_accounts,id',
            'corporate_user_id' => 'required|exists:corporate_users,id',
            'approval_workflow_id' => 'required|exists:approval_workflows,id',
            'max_approvable_amount' => 'nullable|numeric|min:0',
        ]);

        $isPrimary = DB::table('corporate_accounts')->where('id', $validated['corporate_account_id'])->where('primary_user_id', $request->user()->id)->exists();
        if (! $isPrimary) {
            return $this->error('Only primary account holder can assign approval rights', 403);
        }

        $cu = DB::table('corporate_users')->where('id', $validated['corporate_user_id'])->where('corporate_account_id', $validated['corporate_account_id'])->first();
        if (! $cu) {
            return $this->error('Corporate user not in this account', 422);
        }

        $wf = DB::table('approval_workflows')->where('id', $validated['approval_workflow_id'])->where('corporate_account_id', $validated['corporate_account_id'])->first();
        if (! $wf) {
            return $this->error('Workflow not in this account', 422);
        }

        DB::table('user_approval_rights')->updateOrInsert(
            [
                'corporate_user_id' => $validated['corporate_user_id'],
                'approval_workflow_id' => $validated['approval_workflow_id'],
            ],
            ['max_approvable_amount' => $validated['max_approvable_amount'] ?? null, 'updated_at' => now()]
        );

        return $this->success(DB::table('user_approval_rights')->where('corporate_user_id', $validated['corporate_user_id'])->where('approval_workflow_id', $validated['approval_workflow_id'])->first());
    }
}
