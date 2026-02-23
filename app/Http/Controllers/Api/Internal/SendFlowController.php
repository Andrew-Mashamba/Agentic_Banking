<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal API: send a WhatsApp Flow to the client (called by AI or backend).
 * POST /api/internal/send-flow
 * Body: { "flow_type": "transfer"|"loan_application"|"card_block"|"amount_passcode", "body_text": "...", "button_text": "..." }
 * User from X-User-Id (set by InternalApiMiddleware).
 */
class SendFlowController extends BaseApiController
{
    public function __construct(
        protected WhatsAppFlowService $flowService
    ) {
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flow_type' => 'required|in:transfer,loan_application,card_block,amount_passcode,add_beneficiary,recurring_transfer,cardless_withdrawal,loan_repayment,fixed_deposit,investment,card_freeze,card_unfreeze,book_appointment,support_ticket',
            'body_text' => 'nullable|string|max:1024',
            'button_text' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error('User not found', 401);
        }

        $phone = $user->phone_number;
        if (empty($phone)) {
            return $this->error('User has no phone number', 422);
        }

        $result = $this->flowService->sendFlow(
            $phone,
            $validated['flow_type'],
            $validated['body_text'] ?? '',
            $validated['button_text'] ?? 'Open'
        );

        if (isset($result['error'])) {
            return $this->error($result['error'], 400);
        }

        return $this->success([
            'sent' => true,
            'flow_type' => $validated['flow_type'],
            'to' => $phone,
        ], 'Flow sent');
    }
}
