<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F9: Statements & reports – data for custom, tax, interest */
class ReportController extends BaseApiController
{
    public function custom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $user = $request->user();
        $query = Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id));

        if (! empty($validated['account_id'])) {
            $account = Account::where('user_id', $user->id)->find($validated['account_id']);
            if (! $account) {
                return $this->error('Account not found', 404);
            }
            $query->where('account_id', $validated['account_id']);
        }

        $query->whereBetween('created_at', [$validated['from'], $validated['to'] . ' 23:59:59']);
        $transactions = $query->orderBy('created_at')->get();

        return $this->success([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'transactions' => $transactions,
        ]);
    }

    public function tax(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $accountIds = Account::where('user_id', $request->user()->id)->pluck('id');
        $transactions = DB::table('transactions')
            ->whereIn('account_id', $accountIds)
            ->whereYear('created_at', $validated['year'])
            ->orderBy('created_at')
            ->get();

        $interestEarned = 0; // optional: add interest_earned to fixed_deposits or compute from ledger

        return $this->success([
            'year' => $validated['year'],
            'transactions_count' => $transactions->count(),
            'interest_earned' => (float) $interestEarned,
            'transactions' => $transactions,
        ]);
    }

    public function interestCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $accountIds = Account::where('user_id', $request->user()->id)->pluck('id');
        $fdInterest = 0; // optional: add interest_earned to fixed_deposits
        $savingsInterest = DB::table('accounts')
            ->whereIn('id', $accountIds)
            ->where('type', 'savings')
            ->selectRaw('COALESCE(SUM(balance * 0.02), 0) as estimated')
            ->value('estimated');

        return $this->success([
            'year' => $validated['year'],
            'fixed_deposit_interest' => (float) $fdInterest,
            'savings_interest_estimate' => (float) $savingsInterest,
        ]);
    }
}
