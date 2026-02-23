<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** F12: Login history, transaction limits, fraud report, password/PIN/biometric */
class SecurityController extends BaseApiController
{
    public function loginHistory(Request $request): JsonResponse
    {
        $list = DB::table('login_history')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('logged_at')
            ->limit(50)
            ->get();

        return $this->success($list);
    }

    public function transactionLimits(Request $request): JsonResponse
    {
        $rows = DB::table('user_transaction_limits')->where('user_id', $request->user()->id)->get();
        $out = (object) ['per_transaction' => null, 'daily' => null];
        foreach ($rows as $r) {
            if ($r->limit_type === 'per_transaction') {
                $out->per_transaction = (float) $r->amount;
            }
            if ($r->limit_type === 'daily') {
                $out->daily = (float) $r->amount;
            }
        }
        return $this->success($out);
    }

    public function updateTransactionLimits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_transaction_limit' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|numeric|min:0',
        ]);

        $userId = $request->user()->id;
        $now = now();

        if (isset($validated['per_transaction_limit'])) {
            DB::table('user_transaction_limits')->updateOrInsert(
                ['user_id' => $userId, 'limit_type' => 'per_transaction'],
                ['amount' => $validated['per_transaction_limit'], 'currency' => 'USD', 'updated_at' => $now, 'created_at' => $now]
            );
        }
        if (isset($validated['daily_limit'])) {
            DB::table('user_transaction_limits')->updateOrInsert(
                ['user_id' => $userId, 'limit_type' => 'daily'],
                ['amount' => $validated['daily_limit'], 'currency' => 'USD', 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $rows = DB::table('user_transaction_limits')->where('user_id', $userId)->get();
        $out = (object) ['per_transaction' => null, 'daily' => null];
        foreach ($rows as $r) {
            if ($r->limit_type === 'per_transaction') {
                $out->per_transaction = (float) $r->amount;
            }
            if ($r->limit_type === 'daily') {
                $out->daily = (float) $r->amount;
            }
        }
        return $this->success($out);
    }

    public function reportFraud(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:2000',
            'transaction_ids' => 'nullable|array',
            'transaction_ids.*' => 'integer',
        ]);

        $id = DB::table('fraud_reports')->insertGetId([
            'user_id' => $request->user()->id,
            'description' => $validated['description'],
            'status' => 'open',
            'reported_at' => $now = now(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('fraud_reports')->find($id), 'Fraud reported', 201);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return $this->success(null, 'Password updated');
    }

    public function changePin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_pin' => 'required|string|size:4',
            'pin' => 'required|string|size:4|confirmed',
        ]);

        $user = $request->user();
        $currentHash = $user->transaction_pin_hash ?? $user->pin_hash;
        if (! $currentHash || ! Hash::check($validated['current_pin'], $currentHash)) {
            return $this->error('Current PIN is incorrect', 422);
        }

        $user->update(['transaction_pin_hash' => Hash::make($validated['pin'])]);

        return $this->success(null, 'PIN updated');
    }

    public function biometric(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);

        $request->user()->update(['biometric_enabled' => $validated['enabled']]);

        return $this->success(['biometric_enabled' => (bool) $validated['enabled']]);
    }
}
