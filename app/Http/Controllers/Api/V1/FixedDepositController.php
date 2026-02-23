<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F6.2, F6.3, F6.4: Fixed deposits */
class FixedDepositController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $accountIds = Account::where('user_id', $request->user()->id)->pluck('id');
        $list = DB::table('fixed_deposits')
            ->whereIn('account_id', $accountIds)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:1',
            'tenor_months' => 'required|integer|min:1|max:120',
        ]);

        $account = Account::where('user_id', $request->user()->id)->find($validated['account_id']);
        if (! $account) {
            return $this->error('Account not found', 404);
        }

        $maturityDate = now()->addMonths($validated['tenor_months']);
        $id = DB::table('fixed_deposits')->insertGetId([
            'account_id' => $account->id,
            'amount' => $validated['amount'],
            'interest_rate' => 8.5,
            'tenor_months' => $validated['tenor_months'],
            'maturity_date' => $maturityDate,
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('fixed_deposits')->find($id), 'Fixed deposit opened', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $accountIds = Account::where('user_id', $request->user()->id)->pluck('id');
        $fd = DB::table('fixed_deposits')->whereIn('account_id', $accountIds)->find($id);
        if (! $fd) {
            return $this->error('Not found', 404);
        }
        return $this->success($fd);
    }

    public function breakRequest(Request $request, int $id): JsonResponse
    {
        $accountIds = Account::where('user_id', $request->user()->id)->pluck('id');
        $fd = DB::table('fixed_deposits')->whereIn('account_id', $accountIds)->find($id);
        if (! $fd || $fd->status !== 'active') {
            return $this->error('Not found or not active', 404);
        }
        DB::table('fixed_deposits')->where('id', $id)->update(['status' => 'broken', 'break_requested_at' => now(), 'updated_at' => now()]);
        return $this->success(DB::table('fixed_deposits')->find($id), 'Break requested');
    }
}
