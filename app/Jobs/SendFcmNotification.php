<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFcmNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  string  $targetType  'user', 'users', or 'role'
     * @param  array  $targetIds  User IDs (for 'user'/'users') or single role string (for 'role')
     * @param  array  $data  FCM data payload
     */
    public function __construct(
        public string $targetType,
        public array $targetIds,
        public array $data,
    ) {}

    public function handle(FcmService $fcmService): void
    {
        \Log::info('FCM Job: Processing', [
            'target_type' => $this->targetType,
            'target_ids' => $this->targetIds,
            'data_type' => $this->data['type'] ?? 'unknown',
        ]);

        match ($this->targetType) {
            'user' => $fcmService->sendToUser((int) $this->targetIds[0], $this->data),
            'users' => $fcmService->sendToUsers(array_map('intval', $this->targetIds), $this->data),
            'role' => $fcmService->sendToRole((string) ($this->targetIds[0] ?? 'waiter'), $this->data),
            default => \Log::warning("FCM Job: Unknown target type: {$this->targetType}"),
        };

        \Log::info('FCM Job: Completed', ['data_type' => $this->data['type'] ?? 'unknown']);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('FCM Job failed', [
            'target_type' => $this->targetType,
            'data_type' => $this->data['type'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
