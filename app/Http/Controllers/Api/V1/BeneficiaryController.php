<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Beneficiary;
use App\Services\BankingAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeneficiaryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $beneficiaries = $request->user()
            ->beneficiaries()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderBy('name')
            ->get();

        return $this->success($beneficiaries);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:64',
            'bank_code' => 'nullable|string|max:32',
            'bank_name' => 'nullable|string|max:255',
            'type' => 'required|in:same_bank,interbank,mobile_wallet,international',
            'mobile_wallet_provider' => 'nullable|string|max:64',
            'mobile_number' => 'nullable|string|max:32',
        ]);

        $validated['user_id'] = $request->user()->id;
        $beneficiary = Beneficiary::create($validated);

        BankingAuditService::log('beneficiary_add', 'beneficiary', $beneficiary->id, [
            'type' => $beneficiary->type,
        ]);

        return $this->success($beneficiary, 'Beneficiary created', 201);
    }

    public function show(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        if ($beneficiary->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($beneficiary);
    }

    public function update(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        if ($beneficiary->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'account_number' => 'nullable|string|max:64',
            'bank_code' => 'nullable|string|max:32',
            'bank_name' => 'nullable|string|max:255',
            'mobile_wallet_provider' => 'nullable|string|max:64',
            'mobile_number' => 'nullable|string|max:32',
        ]);

        $beneficiary->update($validated);

        return $this->success($beneficiary->fresh());
    }

    public function destroy(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        if ($beneficiary->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        $beneficiary->delete();

        return $this->success(null, 'Beneficiary deleted', 200);
    }
}
