<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F7.3: Gift cards */
class GiftCardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $list = DB::table('gift_cards')
            ->where('purchaser_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'recipient_email' => 'nullable|email',
            'recipient_phone' => 'nullable|string|max:32',
        ]);

        $code = strtoupper(bin2hex(random_bytes(8)));
        $id = DB::table('gift_cards')->insertGetId([
            'purchaser_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'code' => $code,
            'recipient_email' => $validated['recipient_email'] ?? null,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('gift_cards')->find($id), 'Gift card created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('gift_cards')->where('purchaser_id', $request->user()->id)->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }
}
