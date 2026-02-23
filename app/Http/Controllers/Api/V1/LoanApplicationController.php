<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\BankingAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanApplicationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $applications = LoanApplication::where('user_id', $request->user()->id)
            ->with('loanProduct')
            ->latest()
            ->get();

        return $this->success($applications);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount_requested' => 'required|numeric|min:1',
            'tenor_months' => 'required|integer|min:1',
            'purpose' => 'nullable|string|max:255',
        ]);

        $product = LoanProduct::findOrFail($validated['loan_product_id']);
        if (! $product->is_active) {
            return $this->error('Loan product is not available', 400);
        }
        if ($validated['amount_requested'] < $product->min_amount || $validated['amount_requested'] > $product->max_amount) {
            return $this->error("Amount must be between {$product->min_amount} and {$product->max_amount}", 422);
        }

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'pending';
        $application = LoanApplication::create($validated);

        BankingAuditService::log('loan_application', 'loan_application', $application->id, [
            'amount_requested' => $application->amount_requested,
            'tenor_months' => $application->tenor_months,
            'status' => $application->status,
        ]);

        return $this->success($application->load('loanProduct'), 'Application submitted', 201);
    }

    public function show(Request $request, LoanApplication $loanApplication): JsonResponse
    {
        if ($loanApplication->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($loanApplication->load('loanProduct'));
    }
}
