<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()
            ->accounts()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('is_primary', 'desc')
            ->get();

        return $this->success($accounts);
    }

    public function show(Request $request, Account $account): JsonResponse
    {
        if ($account->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($account->load(['transactions' => fn ($q) => $q->latest()->limit(20)]));
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        if ($account->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'nickname' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ]);

        $account->update($validated);

        if (! empty($validated['is_primary']) && $validated['is_primary']) {
            $request->user()->update(['primary_account_id' => $account->id]);
            $request->user()->accounts()->where('id', '!=', $account->id)->update(['is_primary' => false]);
        }

        return $this->success($account->fresh());
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = $user->accounts()->where('status', 'active')->get();
        $totalBalance = $accounts->sum('balance');

        return $this->success([
            'total_balance' => (float) $totalBalance,
            'currency' => $accounts->first()?->currency ?? 'USD',
            'accounts_count' => $accounts->count(),
            'accounts' => $accounts,
        ]);
    }
}
