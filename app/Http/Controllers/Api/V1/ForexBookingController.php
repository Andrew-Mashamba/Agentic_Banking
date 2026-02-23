<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F13.3: Forex booking (corporate) */
class ForexBookingController extends BaseApiController
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
        $list = DB::table('forex_bookings')->where('corporate_user_id', $corporateUserId)->orderByDesc('created_at')->get();
        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'corporate_account_id' => 'required|exists:corporate_accounts,id',
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3',
            'amount' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0',
        ]);

        $corporateUserId = $this->getCorporateUserId($request, $validated['corporate_account_id']);
        if (! $corporateUserId) {
            return $this->error('Not authorized for this corporate account', 403);
        }

        $id = DB::table('forex_bookings')->insertGetId([
            'corporate_user_id' => $corporateUserId,
            'from_currency' => $validated['from_currency'],
            'to_currency' => $validated['to_currency'],
            'amount' => $validated['amount'],
            'rate' => $validated['rate'],
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('forex_bookings')->find($id), 'Forex booking created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $corporateUserIds = DB::table('corporate_users')->where('user_id', $request->user()->id)->pluck('id');
        $row = DB::table('forex_bookings')->whereIn('corporate_user_id', $corporateUserIds)->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }
}
