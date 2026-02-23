<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\GuestConversation;
use App\Models\WhatsAppUserMemory;
use App\Services\WhatsApp\PendingTaskService;
use App\Services\WhatsApp\WhatsAppComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WhatsApp preferences and "clear my data" for authenticated user.
 */
class WhatsAppPreferencesController extends BaseApiController
{
    public function show(Request $request, WhatsAppComplianceService $compliance): JsonResponse
    {
        $user = $request->user();
        $prefs = $compliance->getPreferences($user);
        $data = [
            'prefer_human_agent' => $prefs->prefer_human_agent,
            'disable_long_term_memory' => $prefs->disable_long_term_memory,
            'preferred_language' => $prefs->preferred_language,
            'consent_given_at' => $prefs->consent_given_at?->toIso8601String(),
        ];
        return $this->success($data);
    }

    public function update(Request $request, WhatsAppComplianceService $compliance): JsonResponse
    {
        $validated = $request->validate([
            'prefer_human_agent' => 'sometimes|boolean',
            'disable_long_term_memory' => 'sometimes|boolean',
            'preferred_language' => 'nullable|string|max:8|in:en,sw',
        ]);
        $user = $request->user();
        if (array_key_exists('prefer_human_agent', $validated)) {
            $compliance->setPreferHumanAgent($user, $validated['prefer_human_agent']);
        }
        if (array_key_exists('disable_long_term_memory', $validated)) {
            $compliance->setDisableLongTermMemory($user, $validated['disable_long_term_memory']);
        }
        if (array_key_exists('preferred_language', $validated)) {
            $compliance->setPreferredLanguage($user, $validated['preferred_language'] ?: null);
        }
        $prefs = $compliance->getPreferences($user);
        $data = [
            'prefer_human_agent' => $prefs->prefer_human_agent,
            'disable_long_term_memory' => $prefs->disable_long_term_memory,
            'preferred_language' => $prefs->preferred_language,
        ];
        return $this->success($data, 'Preferences updated.');
    }

    /**
     * Clear WhatsApp conversation history and long-term memory for this user.
     */
    public function clearData(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        GuestConversation::where('guest_id', $userId)->delete();
        WhatsAppUserMemory::where('user_id', $userId)->delete();
        return $this->success(null, 'WhatsApp conversation and memory data have been cleared.');
    }

    /**
     * List pending tasks (for "list my pending tasks" in app).
     */
    public function listPendingTasks(Request $request, PendingTaskService $pendingTaskService): JsonResponse
    {
        $tasks = $pendingTaskService->getPending($request->user());
        return $this->success($tasks->toArray());
    }

    /**
     * Cancel all pending tasks.
     */
    public function cancelAllPendingTasks(Request $request, PendingTaskService $pendingTaskService): JsonResponse
    {
        $count = $pendingTaskService->cancelAllForUser($request->user());
        return $this->success(['cancelled' => $count], "{$count} pending task(s) cancelled.");
    }
}
