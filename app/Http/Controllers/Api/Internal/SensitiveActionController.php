<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\SensitiveActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Internal API: request and confirm sensitive actions with OTP (for AI/WhatsApp flow).
 */
class SensitiveActionController extends BaseApiController
{
    public function __construct(
        protected SensitiveActionService $sensitiveActionService
    ) {
    }

    /**
     * POST /api/internal/sensitive-action-request
     * Body: { "action_type": "transfer"|"card_block"|"loan_application", "payload": { ... } }
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_type' => 'required|in:transfer,card_block,loan_application',
            'payload' => 'required|array',
        ]);

        $userId = $request->user()->id;
        $result = $this->sensitiveActionService->requestAction(
            $userId,
            $validated['action_type'],
            $validated['payload']
        );

        return $this->success($result, 'OTP sent to your registered phone', 202);
    }

    /**
     * POST /api/internal/sensitive-action-confirm
     * Body: { "pending_token": "...", "code": "123456" }
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pending_token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        try {
            $result = $this->sensitiveActionService->confirmAction(
                $validated['pending_token'],
                $validated['code']
            );
            return $this->success($result, 'Action completed');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }
    }
}
