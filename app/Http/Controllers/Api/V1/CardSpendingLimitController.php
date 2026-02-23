<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F4.3: Card spending limits by category */
class CardSpendingLimitController extends BaseApiController
{
    public function index(Request $request, int $cardId): JsonResponse
    {
        $card = Card::where('user_id', $request->user()->id)->find($cardId);
        if (! $card) {
            return $this->error('Card not found', 404);
        }

        $limits = DB::table('card_spending_limits')->where('card_id', $cardId)->get();
        return $this->success($limits);
    }

    public function update(Request $request, int $cardId): JsonResponse
    {
        $validated = $request->validate([
            'limits' => 'required|array|min:1',
            'limits.*.category' => 'required|in:online,international,pos,atm',
            'limits.*.limit_amount' => 'required|numeric|min:0',
        ]);

        $card = Card::where('user_id', $request->user()->id)->find($cardId);
        if (! $card) {
            return $this->error('Card not found', 404);
        }

        $now = now();
        foreach ($validated['limits'] as $limit) {
            DB::table('card_spending_limits')->updateOrInsert(
                ['card_id' => $cardId, 'category' => $limit['category']],
                ['limit_amount' => $limit['limit_amount'], 'created_at' => $now, 'updated_at' => $now]
            );
        }

        return $this->success(DB::table('card_spending_limits')->where('card_id', $cardId)->get());
    }
}
