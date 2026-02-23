<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F7.4: Request money */
class MoneyRequestController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $list = DB::table('money_requests')
            ->where('from_user_id', $request->user()->id)
            ->orWhere('to_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'message' => 'nullable|string|max:500',
        ]);

        if ((int) $validated['recipient_id'] === $request->user()->id) {
            return $this->error('Cannot request from self', 422);
        }

        $id = DB::table('money_requests')->insertGetId([
            'from_user_id' => $request->user()->id,
            'to_user_id' => $validated['recipient_id'],
            'amount' => $validated['amount'],
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('money_requests')->find($id), 'Money request sent', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('money_requests')
            ->where('id', $id)
            ->where(function ($q) use ($request) {
                $q->where('from_user_id', $request->user()->id)
                    ->orWhere('to_user_id', $request->user()->id);
            })
            ->first();

        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['action' => 'required|in:accept,reject']);

        $row = DB::table('money_requests')->where('id', $id)->where('to_user_id', $request->user()->id)->where('status', 'pending')->first();
        if (! $row) {
            return $this->error('Not found or not pending', 404);
        }

        DB::table('money_requests')->where('id', $id)->update([
            'status' => $validated['action'] === 'accept' ? 'accepted' : 'rejected',
            'updated_at' => now(),
        ]);

        return $this->success(DB::table('money_requests')->find($id));
    }
}
