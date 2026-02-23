<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Transaction::whereHas('account', fn ($q) => $q->where('user_id', $user->id));

        if ($request->filled('account_id')) {
            $account = Account::where('user_id', $user->id)->find($request->account_id);
            if (! $account) {
                return $this->error('Account not found', 404);
            }
            $query->where('account_id', $account->id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $transactions = $query->latest()->paginate($perPage);

        return $this->success($transactions);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->account->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($transaction->load('account'));
    }
}
