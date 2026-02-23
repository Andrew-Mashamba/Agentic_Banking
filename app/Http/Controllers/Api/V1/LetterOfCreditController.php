<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F13.1: Letter of Credit application (corporate) */
class LetterOfCreditController extends BaseApiController
{
    private function getCorporateUserId(Request $request, ?int $corporateAccountId = null): ?int
    {
        $q = DB::table('corporate_users')->where('user_id', $request->user()->id);
        if ($corporateAccountId) {
            $q->where('corporate_account_id', $corporateAccountId);
        }
        return $q->value('id');
    }

    public function index(Request $request): JsonResponse
    {
        $corporateUserId = $this->getCorporateUserId($request, $request->input('corporate_account_id'));
        if (! $corporateUserId) {
            return $this->success([]);
        }
        $list = DB::table('letter_of_credit_applications')->where('corporate_user_id', $corporateUserId)->orderByDesc('created_at')->get();
        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'corporate_account_id' => 'required|exists:corporate_accounts,id',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
            'details' => 'nullable|array',
        ]);

        $corporateUserId = $this->getCorporateUserId($request, $validated['corporate_account_id']);
        if (! $corporateUserId) {
            return $this->error('Not authorized for this corporate account', 403);
        }

        $id = DB::table('letter_of_credit_applications')->insertGetId([
            'corporate_user_id' => $corporateUserId,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'USD',
            'details' => json_encode($validated['details'] ?? []),
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('letter_of_credit_applications')->find($id), 'Application submitted', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $corporateUserIds = DB::table('corporate_users')->where('user_id', $request->user()->id)->pluck('id');
        $row = DB::table('letter_of_credit_applications')->whereIn('corporate_user_id', $corporateUserIds)->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }
}
