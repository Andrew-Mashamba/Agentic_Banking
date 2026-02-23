<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Card;
use App\Services\BankingAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $cards = $request->user()->cards()->with('account')->get();

        return $this->success($cards);
    }

    public function show(Request $request, Card $card): JsonResponse
    {
        if ($card->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($card->load('account'));
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        if ($card->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:active,frozen,blocked',
            'daily_limit_amount' => 'nullable|numeric|min:0',
            'online_enabled' => 'boolean',
            'international_enabled' => 'boolean',
        ]);

        $card->update($validated);

        return $this->success($card->fresh());
    }

    public function freeze(Card $card, Request $request): JsonResponse
    {
        if ($card->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }
        $card->update(['status' => 'frozen']);
        BankingAuditService::log('card_freeze', 'card', $card->id, ['last_four' => $card->last_four]);
        return $this->success($card->fresh(), 'Card frozen');
    }

    public function unfreeze(Card $card, Request $request): JsonResponse
    {
        if ($card->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }
        $card->update(['status' => 'active']);
        BankingAuditService::log('card_unfreeze', 'card', $card->id, ['last_four' => $card->last_four]);
        return $this->success($card->fresh(), 'Card unfrozen');
    }

    public function block(Card $card, Request $request): JsonResponse
    {
        if ($card->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }
        $card->update(['status' => 'blocked']);
        BankingAuditService::log('card_block', 'card', $card->id, ['last_four' => $card->last_four]);
        return $this->success($card->fresh(), 'Card blocked');
    }
}
