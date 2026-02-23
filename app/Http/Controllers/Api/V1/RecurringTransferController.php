<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use App\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F3.7: Recurring transfer / standing orders */
class RecurringTransferController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $items = DB::table('recurring_transfers')
            ->where('user_id', $request->user()->id)
            ->orderBy('next_run_at')
            ->get();

        return $this->success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'beneficiary_id' => 'required|exists:beneficiaries,id',
            'amount' => 'required|numeric|min:0.01',
            'frequency' => 'required|in:daily,weekly,monthly',
            'end_at' => 'nullable|date',
        ]);

        $user = $request->user();
        if (Account::where('user_id', $user->id)->where('id', $validated['from_account_id'])->doesntExist()) {
            return $this->error('Account not found', 404);
        }
        if (Beneficiary::where('user_id', $user->id)->where('id', $validated['beneficiary_id'])->doesntExist()) {
            return $this->error('Beneficiary not found', 404);
        }

        $next = match ($validated['frequency']) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            default => now()->addMonth(),
        };
        $id = DB::table('recurring_transfers')->insertGetId([
            'user_id' => $user->id,
            'from_account_id' => $validated['from_account_id'],
            'beneficiary_id' => $validated['beneficiary_id'],
            'amount' => $validated['amount'],
            'frequency' => $validated['frequency'],
            'next_run_at' => $next,
            'end_at' => $validated['end_at'] ?? null,
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('recurring_transfers')->find($id), 'Recurring transfer created', 201);
    }

    public function show(Request $request, int $recurring_transfer): JsonResponse
    {
        $row = DB::table('recurring_transfers')->where('user_id', $request->user()->id)->find($recurring_transfer);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }

    public function update(Request $request, int $recurring_transfer): JsonResponse
    {
        $row = DB::table('recurring_transfers')->where('user_id', $request->user()->id)->find($recurring_transfer);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'frequency' => 'sometimes|in:daily,weekly,monthly',
            'end_at' => 'nullable|date',
            'status' => 'sometimes|in:active,paused,cancelled',
        ]);
        DB::table('recurring_transfers')->where('id', $recurring_transfer)->update(array_merge($validated, ['updated_at' => now()]));
        return $this->success(DB::table('recurring_transfers')->find($recurring_transfer));
    }

    public function destroy(Request $request, int $recurring_transfer): JsonResponse
    {
        $deleted = DB::table('recurring_transfers')->where('user_id', $request->user()->id)->where('id', $recurring_transfer)->delete();
        if (! $deleted) {
            return $this->error('Not found', 404);
        }
        return $this->success(null, 'Recurring transfer cancelled');
    }
}
