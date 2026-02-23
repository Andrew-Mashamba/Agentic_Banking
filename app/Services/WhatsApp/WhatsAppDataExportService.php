<?php

namespace App\Services\WhatsApp;

use App\Models\GuestConversation;
use App\Models\User;
use App\Models\WhatsAppPendingTask;
use App\Models\WhatsAppUserMemory;
use App\Models\WhatsAppUserPreferences;
use Illuminate\Support\Carbon;

/**
 * Export WhatsApp-related data for a user (GDPR-style portability).
 */
class WhatsAppDataExportService
{
    public function __construct(
        protected WhatsAppComplianceService $compliance
    ) {}

    /**
     * Gather all WhatsApp-related data for the user (no file contents, metadata only for attachments).
     */
    public function exportForUser(User $user): array
    {
        $userId = $user->id;
        $exportedAt = Carbon::now()->toIso8601String();

        $conversations = GuestConversation::where('guest_id', $userId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($row) => [
                'role' => $row->role,
                'content' => $row->content,
                'message_type' => $row->message_type ?? 'text',
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $memoryRow = WhatsAppUserMemory::where('user_id', $userId)->first();
        $longTermMemory = $memoryRow && $memoryRow->memory_text ? $memoryRow->memory_text : '';

        $pendingTasks = WhatsAppPendingTask::where('user_id', $userId)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'task_type' => $t->task_type,
                'step' => $t->step,
                'status' => $t->status,
                'created_at' => $t->created_at?->toIso8601String(),
                'updated_at' => $t->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $prefs = WhatsAppUserPreferences::where('user_id', $userId)->first();
        $preferences = $prefs ? [
            'consent_given_at' => $prefs->consent_given_at?->toIso8601String(),
            'consent_version' => $prefs->consent_version,
            'prefer_human_agent' => $prefs->prefer_human_agent,
            'disable_long_term_memory' => $prefs->disable_long_term_memory,
            'preferred_language' => $prefs->preferred_language,
        ] : [];

        return [
            'exported_at' => $exportedAt,
            'user_id' => $userId,
            'conversations' => $conversations,
            'long_term_memory_text' => $longTermMemory,
            'pending_tasks' => $pendingTasks,
            'preferences' => $preferences,
        ];
    }
}
