<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Services\WhatsApp\PendingTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal API: manage pending tasks for WhatsApp AI (resumable workflows).
 * User from X-User-Id. AI calls these to register/update/complete tasks.
 */
class PendingTaskController extends BaseApiController
{
    public function __construct(
        protected PendingTaskService $pendingTaskService
    ) {
    }

    /**
     * GET /api/internal/pending-tasks — List pending tasks for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error('User not found', 401);
        }

        $tasks = $this->pendingTaskService->getPending($user);

        return $this->success($tasks->toArray());
    }

    /**
     * POST /api/internal/pending-tasks — Create or update a pending task.
     * Body: { task_type, step?, context? }
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error('User not found', 401);
        }

        $validated = $request->validate([
            'task_type' => 'required|string|max:64',
            'step' => 'nullable|string|max:128',
            'context' => 'nullable|array',
        ]);

        $task = $this->pendingTaskService->createOrUpdate(
            $user,
            $validated['task_type'],
            $validated['step'] ?? null,
            $validated['context'] ?? []
        );

        return $this->success($task->toArray(), 'Pending task saved', 201);
    }

    /**
     * POST /api/internal/pending-tasks/{id}/complete — Mark task completed or abandoned.
     * Body: { status?: "completed"|"abandoned" }
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error('User not found', 401);
        }

        $status = $request->input('status', 'completed');
        if (! in_array($status, ['completed', 'abandoned'], true)) {
            $status = 'completed';
        }

        $ok = $this->pendingTaskService->complete($id, $user, $status);
        if (! $ok) {
            return $this->error('Task not found', 404);
        }

        return $this->success(['completed' => true, 'status' => $status]);
    }

    /**
     * POST /api/internal/pending-tasks/cancel-all — Mark all pending tasks as abandoned.
     */
    public function cancelAll(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error('User not found', 401);
        }
        $count = $this->pendingTaskService->cancelAllForUser($user);
        return $this->success(['cancelled' => $count]);
    }
}
