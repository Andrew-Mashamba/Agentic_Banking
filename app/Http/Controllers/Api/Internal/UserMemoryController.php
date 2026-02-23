<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Services\WhatsApp\UserMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal API: long-term memory for WhatsApp AI (OpenClaw-style).
 * User from X-User-Id. AI can read memory (injected in prompt automatically) or append via this API.
 */
class UserMemoryController extends BaseApiController
{
    public function __construct(
        protected UserMemoryService $userMemoryService
    ) {
    }

    /**
     * GET /api/internal/user-memory — Get current memory (for prompt or editing).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error('User not found', 401);
        }

        $memory = $this->userMemoryService->get($user);

        return $this->success(['memory_text' => $memory]);
    }

    /**
     * POST /api/internal/user-memory — Append to memory (e.g. "Client prefers TZS").
     * Body: { text } or { memory_text }
     */
    public function append(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error('User not found', 401);
        }

        $text = $request->input('text') ?? $request->input('memory_text') ?? '';
        $text = is_string($text) ? trim($text) : '';
        if ($text === '') {
            return $this->error('Text is required', 422);
        }

        $this->userMemoryService->append($user, $text);

        return $this->success(['appended' => true]);
    }
}
