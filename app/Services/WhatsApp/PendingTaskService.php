<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppPendingTask;
use Illuminate\Support\Collection;

/**
 * Pending multi-step tasks (account opening, loan application, KYC, etc.).
 * Keyed by user_id (resolved from phone_number). Enables resume after disconnect or days later.
 * Enforces pending_task_max_per_user by abandoning oldest task when at cap.
 */
class PendingTaskService
{
    public function __construct(
        protected WhatsAppComplianceService $complianceService
    ) {}
    /**
     * Get pending tasks for the user for inclusion in the AI prompt.
     */
    public function getForPrompt(User $user): string
    {
        $tasks = $this->getPending($user);

        if ($tasks->isEmpty()) {
            return '';
        }

        $lines = ["=== PENDING TASKS (offer to continue) ==="];
        foreach ($tasks as $task) {
            $step = $task->step ? " — step: {$task->step}" : '';
            $ctx = is_array($task->context) && ! empty($task->context)
                ? ' (e.g. ' . implode(', ', array_slice(array_keys($task->context), 0, 3)) . ')'
                : '';
            $lines[] = "- {$task->task_type}{$step}{$ctx} — updated " . $task->updated_at->diffForHumans();
        }
        $lines[] = "Ask the client if they want to continue with any of these before starting something new.";
        $lines[] = "=== END PENDING TASKS ===";

        return implode("\n", $lines);
    }

    /**
     * Get all pending tasks for the user.
     */
    public function getPending(User $user): Collection
    {
        return WhatsAppPendingTask::where('user_id', $user->id)
            ->where('status', WhatsAppPendingTask::STATUS_PENDING)
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Create or update a pending task (upsert by user + task_type for same flow).
     */
    public function createOrUpdate(User $user, string $taskType, ?string $step = null, array $context = []): WhatsAppPendingTask
    {
        $phone = $user->phone_number ?? '';

        $task = WhatsAppPendingTask::where('user_id', $user->id)
            ->where('task_type', $taskType)
            ->where('status', WhatsAppPendingTask::STATUS_PENDING)
            ->first();

        if ($task) {
            $task->update([
                'step' => $step ?? $task->step,
                'context' => array_merge($task->context ?? [], $context),
                'phone_number' => $phone,
                'updated_at' => now(),
            ]);
            return $task->fresh();
        }

        $max = $this->complianceService->pendingTaskMaxPerUser();
        $pending = $this->getPending($user);
        if ($pending->count() >= $max) {
            $oldest = WhatsAppPendingTask::where('user_id', $user->id)
                ->where('status', WhatsAppPendingTask::STATUS_PENDING)
                ->orderBy('updated_at')
                ->first();
            if ($oldest) {
                $oldest->update(['status' => WhatsAppPendingTask::STATUS_ABANDONED]);
            }
        }

        return WhatsAppPendingTask::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'task_type' => $taskType,
            'step' => $step,
            'context' => $context,
            'status' => WhatsAppPendingTask::STATUS_PENDING,
        ]);
    }

    /**
     * Mark a task completed or abandoned.
     */
    public function complete(int $taskId, User $user, string $status = WhatsAppPendingTask::STATUS_COMPLETED): bool
    {
        $task = WhatsAppPendingTask::where('id', $taskId)->where('user_id', $user->id)->first();
        if (! $task) {
            return false;
        }
        $task->update(['status' => $status]);
        return true;
    }

    /**
     * Complete by task_type (e.g. when flow finishes).
     */
    public function completeByType(User $user, string $taskType): bool
    {
        $updated = WhatsAppPendingTask::where('user_id', $user->id)
            ->where('task_type', $taskType)
            ->where('status', WhatsAppPendingTask::STATUS_PENDING)
            ->update(['status' => WhatsAppPendingTask::STATUS_COMPLETED]);
        return $updated > 0;
    }

    /**
     * Get one pending task by type (for resuming).
     */
    public function getOnePendingByType(User $user, string $taskType): ?WhatsAppPendingTask
    {
        return WhatsAppPendingTask::where('user_id', $user->id)
            ->where('task_type', $taskType)
            ->where('status', WhatsAppPendingTask::STATUS_PENDING)
            ->first();
    }

    /**
     * Link an attachment to the user's most recently updated pending task (for resume context).
     */
    public function appendAttachmentToLatestTask(User $user, string $attachmentPath, string $attachmentType, ?string $caption = null): void
    {
        $task = WhatsAppPendingTask::where('user_id', $user->id)
            ->where('status', WhatsAppPendingTask::STATUS_PENDING)
            ->orderByDesc('updated_at')
            ->first();
        if (! $task) {
            return;
        }
        $context = $task->context ?? [];
        $attachments = $context['attachments'] ?? [];
        $attachments[] = [
            'path' => $attachmentPath,
            'type' => $attachmentType,
            'caption' => $caption,
            'at' => now()->toIso8601String(),
        ];
        $context['attachments'] = $attachments;
        $task->update(['context' => $context, 'updated_at' => now()]);
    }

    /**
     * Mark all pending tasks for the user as abandoned (cancel all).
     */
    public function cancelAllForUser(User $user): int
    {
        return WhatsAppPendingTask::where('user_id', $user->id)
            ->where('status', WhatsAppPendingTask::STATUS_PENDING)
            ->update(['status' => WhatsAppPendingTask::STATUS_ABANDONED]);
    }

    /**
     * Fallback message when AI fails or is unavailable; appends pending tasks if any.
     */
    public function getFallbackMessageForUser(User $user, bool $unavailable = false): string
    {
        $base = $unavailable
            ? 'The assistant is temporarily unavailable. Please try again in a few minutes.'
            : 'Sorry, I couldn\'t process that. Please try again or contact support.';
        $pending = $this->getPending($user);
        if ($pending->isEmpty()) {
            return $base;
        }
        $list = $pending->take(3)->map(fn ($t) => $t->task_type)->join(', ');
        $count = $pending->count();
        $suffix = $count === 1
            ? " You have an incomplete task: {$list}. Reply 'continue' when ready."
            : " You have {$count} incomplete task(s): {$list}. Reply 'continue' to resume.";
        return $base . $suffix;
    }
}
