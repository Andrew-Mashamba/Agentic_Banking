<?php

namespace App\Jobs;

use App\Exceptions\SidecarUnavailableException;
use App\Models\User;
use App\Services\WhatsApp\AiAgentService;
use App\Services\WhatsApp\ConversationManager;
use App\Services\WhatsApp\CachedFallbackService;
use App\Services\WhatsApp\PendingTaskService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Process a WhatsApp message via the AI agent asynchronously.
 * Dispatched from MessageHandler; webhook returns 200 immediately.
 */
class ProcessAiMessage implements ShouldQueue
{
    use Queueable;

    public int $userId;

    public array $messageData;

    public string $currentState;

    public int $tries = 3;

    public int $timeout = 150;

    public function __construct(int $userId, array $messageData, string $currentState)
    {
        $this->userId = $userId;
        $this->messageData = $messageData;
        $this->currentState = $currentState;
        $this->onQueue('ai');
    }

    /**
     * Exponential backoff with jitter: ~5s, ~15s, ~45s.
     */
    public function backoff(): array
    {
        return [
            5 + random_int(0, 2),
            15 + random_int(0, 5),
            45 + random_int(0, 10),
        ];
    }

    public function handle(
        AiAgentService $aiService,
        WhatsAppService $whatsAppService,
        ConversationManager $conversationManager
    ): void {
        $user = User::find($this->userId);

        if (!$user) {
            Log::channel('whatsapp')->error('ProcessAiMessage: user not found', ['user_id' => $this->userId]);
            return;
        }

        $phone = $user->phone_number;
        $whatsAppService->sendTypingIndicator($phone);

        Log::channel('whatsapp')->info('ProcessAiMessage: starting AI processing', [
            'user_id' => $user->id,
            'phone' => $phone,
        ]);

        try {
            $handled = $aiService->processMessage($user, $this->messageData);
            if ($handled) {
                $conversationManager->updateSessionData($phone, 'consecutive_ai_failures', 0);
                Log::channel('whatsapp')->info('ProcessAiMessage: AI handled successfully', ['user_id' => $user->id]);
                return;
            }
        } catch (SidecarUnavailableException $e) {
            if ($this->attempts() >= $this->tries) {
                $this->sendUnavailableOrFallback($user, $phone, $whatsAppService);
                return;
            }
            Log::channel('whatsapp')->warning('ProcessAiMessage: sidecar unavailable (will retry)', [
                'user_id' => $user->id,
                'attempt' => $this->attempts(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('ProcessAiMessage: AI exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // AI did not handle — increment consecutive failures and send generic reply (mention pending tasks if any)
        app(\App\Services\WhatsApp\WhatsAppMetricsService::class)->incrementAiFailures();
        $sessionData = $conversationManager->getSessionData($phone);
        $failures = (int) ($sessionData['consecutive_ai_failures'] ?? 0);
        $conversationManager->updateSessionData($phone, 'consecutive_ai_failures', $failures + 1);

        $fallback = app(PendingTaskService::class)->getFallbackMessageForUser($user);
        try {
            $whatsAppService->sendTextMessage($phone, $fallback);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->warning('ProcessAiMessage: failed to send fallback message', ['error' => $e->getMessage()]);
        }
    }

    protected function sendUnavailableOrFallback(User $user, string $phone, WhatsAppService $whatsAppService): void
    {
        $cached = app(CachedFallbackService::class);
        $reply = $cached->tryReply($user, $this->messageData);
        if ($reply !== null) {
            try {
                $whatsAppService->sendTextMessage($phone, $reply);
            } catch (\Exception $e) {
                Log::channel('whatsapp')->debug('Cached fallback send failed', ['error' => $e->getMessage()]);
            }
            return;
        }
        app(\App\Services\WhatsApp\WhatsAppMetricsService::class)->incrementAiFailures();
        $msg = app(PendingTaskService::class)->getFallbackMessageForUser($user, true);
        try {
            $whatsAppService->sendTextMessage($phone, $msg);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->warning('ProcessAiMessage: failed to send unavailable message', ['error' => $e->getMessage()]);
        }
    }
}
