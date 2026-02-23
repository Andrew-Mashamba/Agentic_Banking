<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends BaseApiController
{
    public function products(Request $request): JsonResponse
    {
        $products = LoanProduct::where('is_active', true)->get();

        return $this->success($products);
    }

    public function index(Request $request): JsonResponse
    {
        $loans = $request->user()->loans()->with('loanProduct', 'account')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        return $this->success($loans);
    }

    public function show(Request $request, Loan $loan): JsonResponse
    {
        if ($loan->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($loan->load(['loanProduct', 'account', 'repaymentSchedules']));
    }

    public function repaymentSchedule(Request $request, Loan $loan): JsonResponse
    {
        if ($loan->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        $schedule = $loan->repaymentSchedules()->orderBy('due_date')->get();

        return $this->success($schedule);
    }
}
