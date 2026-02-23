<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F3.10: Bulk payments (salary / payroll) */
class BulkPaymentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $batches = DB::table('bulk_payment_batches')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($batches);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.beneficiary_id' => 'required|exists:beneficiaries,id',
            'items.*.amount' => 'required|numeric|min:0.01',
        ]);

        $user = $request->user();
        $beneficiaryIds = DB::table('beneficiaries')->where('user_id', $user->id)->pluck('id')->toArray();
        $total = 0;
        foreach ($validated['items'] as $item) {
            if (! in_array($item['beneficiary_id'], $beneficiaryIds)) {
                return $this->error('Invalid beneficiary in items', 422);
            }
            $total += (float) $item['amount'];
        }

        $batchId = DB::table('bulk_payment_batches')->insertGetId([
            'user_id' => $user->id,
            'name' => $validated['name'] ?? null,
            'total_amount' => $total,
            'total_count' => count($validated['items']),
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        foreach ($validated['items'] as $item) {
            DB::table('bulk_payment_items')->insert([
                'bulk_payment_batch_id' => $batchId,
                'beneficiary_id' => $item['beneficiary_id'],
                'amount' => $item['amount'],
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->success(DB::table('bulk_payment_batches')->find($batchId), 'Bulk payment created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $batch = DB::table('bulk_payment_batches')->where('user_id', $request->user()->id)->find($id);
        if (! $batch) {
            return $this->error('Not found', 404);
        }
        $items = DB::table('bulk_payment_items')->where('bulk_payment_batch_id', $id)->get();
        return $this->success(['batch' => $batch, 'items' => $items]);
    }
}
