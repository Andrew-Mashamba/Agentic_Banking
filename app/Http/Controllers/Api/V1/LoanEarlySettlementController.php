<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F5.5: Early settlement request */
class LoanEarlySettlementController extends BaseApiController
{
    public function index(Request $request, int $loanId): JsonResponse
    {
        $loan = Loan::where('user_id', $request->user()->id)->find($loanId);
        if (! $loan) {
            return $this->error('Loan not found', 404);
        }
        $list = DB::table('early_settlement_requests')->where('loan_id', $loanId)->orderByDesc('created_at')->get();
        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
        ]);

        $loan = Loan::where('user_id', $request->user()->id)->find($validated['loan_id']);
        if (! $loan) {
            return $this->error('Loan not found', 404);
        }

        $settlementAmount = $loan->outstanding_balance; // simplified; could add fee calculation

        $id = DB::table('early_settlement_requests')->insertGetId([
            'loan_id' => $loan->id,
            'requested_at' => now(),
            'settlement_amount' => $settlementAmount,
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('early_settlement_requests')->find($id), 'Early settlement requested', 201);
    }
}
