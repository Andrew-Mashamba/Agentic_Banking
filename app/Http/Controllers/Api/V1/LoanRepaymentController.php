<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F3.16: Loan repayment */
class LoanRepaymentController extends BaseApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:accounts,id',
        ]);

        $user = $request->user();
        $loan = Loan::where('user_id', $user->id)->find($validated['loan_id']);
        if (! $loan) {
            return $this->error('Loan not found', 404);
        }
        $account = Account::where('user_id', $user->id)->find($validated['account_id']);
        if (! $account) {
            return $this->error('Account not found', 404);
        }

        $id = DB::table('loan_repayments')->insertGetId([
            'loan_id' => $loan->id,
            'amount' => $validated['amount'],
            'paid_at' => now(),
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        $repayment = DB::table('loan_repayments')->find($id);
        return $this->success($repayment, 'Repayment recorded', 201);
    }
}
