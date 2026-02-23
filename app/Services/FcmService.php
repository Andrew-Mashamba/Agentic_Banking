<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class FcmService
{
    protected Messaging $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Send a data-only FCM message to a specific user's devices.
     */
    public function sendToUser(int $userId, array $data): void
    {
        $tokens = DeviceToken::where('user_id', $userId)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $this->sendToTokens($tokens, $data);
    }

    /**
     * Send a data-only FCM message to multiple users.
     */
    public function sendToUsers(array $userIds, array $data): void
    {
        $tokens = DeviceToken::whereIn('user_id', $userIds)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $this->sendToTokens($tokens, $data);
    }

    /**
     * Send to users by role (e.g. waiters). Uses User model and status when present.
     */
    public function sendToRole(string $role, array $data): void
    {
        $query = User::where('role', $role);
        if (\Schema::hasColumn('users', 'status')) {
            $query->where('status', 'active');
        }
        $userIds = $query->pluck('id')->toArray();
        $this->sendToUsers($userIds, $data);
    }

    /**
     * Send a data-only message to specific FCM tokens via multicast.
     * Automatically cleans up invalid/expired tokens.
     */
    protected function sendToTokens(array $tokens, array $data): void
    {
        // FCM data values must all be strings
        $stringData = array_map('strval', $data);

        $message = CloudMessage::new()->withData($stringData);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);

            // Clean up invalid tokens
            $invalidTokens = [];

            foreach ($report->failures()->getItems() as $failure) {
                $errorCode = $failure->error()?->getMessage() ?? '';

                if (str_contains($errorCode, 'UNREGISTERED')
                    || str_contains($errorCode, 'INVALID_ARGUMENT')
                    || str_contains($errorCode, 'NOT_FOUND')) {
                    $target = $failure->target()?->value();
                    if ($target) {
                        $invalidTokens[] = $target;
                    }
                }
            }

            if (! empty($invalidTokens)) {
                $deleted = DeviceToken::whereIn('fcm_token', $invalidTokens)->delete();
                Log::info("FCM: Cleaned up {$deleted} invalid tokens");
            }

            Log::info('FCM: Multicast sent', [
                'successes' => $report->successes()->count(),
                'failures' => $report->failures()->count(),
                'type' => $data['type'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            Log::error('FCM: Send failed', [
                'error' => $e->getMessage(),
                'token_count' => count($tokens),
            ]);
        }
    }
}
