<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use App\Services\BankingAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F3.15: Cardless ATM withdrawal */
class CardlessWithdrawalController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $list = DB::table('cardless_withdrawals')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $account = Account::where('user_id', $request->user()->id)->find($validated['account_id']);
        if (! $account) {
            return $this->error('Account not found', 404);
        }

        $code = str_pad((string) random_int(100000, 999999), 6, '0');
        $expiresAt = now()->addHours(2);

        $id = DB::table('cardless_withdrawals')->insertGetId([
            'user_id' => $request->user()->id,
            'account_id' => $validated['account_id'],
            'amount' => $validated['amount'],
            'code' => $code,
            'expires_at' => $expiresAt,
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        BankingAuditService::log('cardless_withdrawal', 'cardless_withdrawal', (int) $id, [
            'amount' => $validated['amount'],
            'status' => 'pending',
        ]);

        $row = DB::table('cardless_withdrawals')->find($id);
        $row->expires_at = $expiresAt->toIso8601String();

        return $this->success($row, 'Cardless withdrawal code generated', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('cardless_withdrawals')->where('user_id', $request->user()->id)->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }
}
