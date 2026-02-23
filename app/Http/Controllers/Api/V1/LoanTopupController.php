<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F5.6: Loan top-up request */
class LoanTopupController extends BaseApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'amount_requested' => 'required|numeric|min:1',
        ]);

        $loan = Loan::where('user_id', $request->user()->id)->find($validated['loan_id']);
        if (! $loan) {
            return $this->error('Loan not found', 404);
        }

        $id = DB::table('loan_topup_requests')->insertGetId([
            'loan_id' => $loan->id,
            'amount_requested' => $validated['amount_requested'],
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('loan_topup_requests')->find($id), 'Top-up requested', 201);
    }
}
