<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessAiMessage;
use App\Models\User;
use App\Models\Setting;
use App\Services\WhatsApp\EscalationService;
use App\Services\WhatsApp\WhatsAppComplianceService;
use App\Services\WhatsApp\PendingTaskService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class MessageHandler
{
    protected WhatsAppService $whatsappService;

    protected ConversationManager $conversationManager;

    protected EscalationService $escalationService;

    protected WhatsAppComplianceService $complianceService;

    protected PendingTaskService $pendingTaskService;

    public function __construct(
        WhatsAppService $whatsappService,
        ConversationManager $conversationManager,
        EscalationService $escalationService,
        WhatsAppComplianceService $complianceService,
        PendingTaskService $pendingTaskService
    ) {
        $this->whatsappService = $whatsappService;
        $this->conversationManager = $conversationManager;
        $this->escalationService = $escalationService;
        $this->complianceService = $complianceService;
        $this->pendingTaskService = $pendingTaskService;
    }

    /**
     * Handle incoming WhatsApp message
     */
    public function handle(array $message, array $context): void
    {
        $messageId = $message['id'] ?? null;
        $from = $message['from'] ?? null;
        $timestamp = $message['timestamp'] ?? null;
        $type = $message['type'] ?? 'unknown';

        Log::channel('whatsapp')->info('--- MessageHandler::handle() START ---', [
            'from' => $from,
            'type' => $type,
            'message_id' => $messageId,
            'timestamp' => $timestamp,
        ]);

        if (! $from) {
            Log::channel('whatsapp')->warning('Message received without sender — skipping');

            return;
        }

        // Mark message as read
        if ($messageId) {
            Log::channel('whatsapp')->info('Marking message as read', ['message_id' => $messageId]);
            try {
                $this->whatsappService->markAsRead($messageId);
                Log::channel('whatsapp')->info('Message marked as read OK');
            } catch (\Exception $e) {
                Log::channel('whatsapp')->warning('Failed to mark as read (non-fatal)', ['error' => $e->getMessage()]);
            }
        }

        // Get user by phone number (banking clients must be registered)
        Log::channel('whatsapp')->info('Looking up user', ['phone' => $from]);
        $user = $this->getUserByPhone($from, $context);

        if (!$user) {
            Log::channel('whatsapp')->warning('User not found for phone', ['phone' => $from]);
            try {
                $this->whatsappService->sendTextMessage($from,
                    "Welcome to our banking service. To use WhatsApp banking, please register through our mobile app or visit a branch. " .
                    "If you're already a customer, ensure your phone number is registered with us."
                );
            } catch (\Exception $e) {
                Log::channel('whatsapp')->warning('Failed to send registration message', ['error' => $e->getMessage()]);
            }
            return;
        }

        Log::channel('whatsapp')->info('User resolved', [
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        // Get session (and whether it was just expired) then link user
        $session = $this->conversationManager->getSession($from);
        $this->conversationManager->linkGuest($from, $user->id);
        $wasExpired = $session->wasExpired ?? false;

        // Get current conversation state
        $state = $this->conversationManager->getState($from);
        $sessionData = $this->conversationManager->getSessionData($from);
        Log::channel('whatsapp')->info('Session state', [
            'state' => $state,
            'session_data_keys' => array_keys($sessionData),
        ]);

        // Process message based on type
        $messageData = $this->extractMessageData($message, $type);

        // Download and store attachments (image, document) to predefined storage path
        if (in_array($type, ['image', 'document'], true) && ! empty($messageData['media_id'])) {
            $extension = '';
            if (! empty($messageData['filename']) && preg_match('/\.([a-z0-9]+)$/i', $messageData['filename'], $m)) {
                $extension = $m[1];
            }
            $savedPath = $this->whatsappService->downloadAndSaveMedia(
                $messageData['media_id'],
                $messageData['mime_type'] ?? '',
                $user->id,
                $extension
            );
            if ($savedPath) {
                $messageData['attachment_path'] = $savedPath;
                $messageData['attachment_type'] = $type;
                $messageData['attachment_caption'] = $messageData['caption'] ?? null;
                $messageData['text'] = $messageData['text'] ?? '[Client sent an attachment. Stored. ' . ($messageData['attachment_caption'] ? 'They said: ' . $messageData['attachment_caption'] : 'Ask them what this file is (e.g. national ID, pay slip).') . ']';
                $this->pendingTaskService->appendAttachmentToLatestTask($user, $savedPath, $type, $messageData['attachment_caption'] ?? null);
            }
            unset($messageData['media_id'], $messageData['caption'], $messageData['filename']);
        }

        Log::channel('whatsapp')->info('Extracted message data', array_diff_key($messageData, ['attachment_path' => 1]));

        // ── Deduplication ──
        // WhatsApp retries webhooks if we don't respond fast enough.
        // Skip messages we've already seen (keyed by wamid, TTL 10 min).
        if ($messageId) {
            $dedupKey = "wa-msg-seen:{$messageId}";
            if (Cache::has($dedupKey)) {
                Log::channel('whatsapp')->info('Duplicate message skipped', [
                    'message_id' => $messageId,
                ]);

                return;
            }
            Cache::put($dedupKey, true, 600); // 10 minutes
        }

        // ── Rate limiting (per-phone per minute, optional per-user per day) ──
        if ($this->isRateLimited($from, $user->id)) {
            Log::channel('whatsapp')->info('Rate limit exceeded', ['phone' => $from, 'user_id' => $user->id]);
            try {
                $this->whatsappService->sendTextMessage($from, 'Too many messages. Please wait a moment before sending again.');
            } catch (\Exception $e) {
                Log::channel('whatsapp')->warning('Failed to send rate-limit message', ['error' => $e->getMessage()]);
            }
            return;
        }

        // ── Human handoff: offer after consecutive AI failures ──
        $userText = trim($messageData['text'] ?? $messageData['button_title'] ?? $messageData['list_title'] ?? '');
        $consecutiveFailures = (int) ($sessionData['consecutive_ai_failures'] ?? 0);
        if ($state !== EscalationService::STATE_OFFER_HANDOFF && $consecutiveFailures >= 2) {
            $this->conversationManager->setState($from, EscalationService::STATE_OFFER_HANDOFF, $sessionData);
            try {
                $this->whatsappService->sendTextMessage($from,
                    "I'm having trouble helping with that. Would you like to speak to a human agent? Reply YES to connect."
                );
            } catch (\Exception $e) {
                Log::channel('whatsapp')->warning('Failed to send handoff offer', ['error' => $e->getMessage()]);
            }
            return;
        }

        // ── Consent: first-time disclosure (override via whatsapp_consent_required = false) ──
        if ($this->complianceService->consentRequired() && ! $this->complianceService->hasConsented($user)) {
            if ($state === EscalationService::STATE_AWAITING_CONSENT) {
                $agree = preg_match('/\b(yes|yeah|yep|agree|accept|continue|ok|okay|i agree)\b/i', $userText);
                if ($agree) {
                    $consentParams = null;
                    if ($this->complianceService->isOpenBankingConsentEnabled()) {
                        $params = $this->complianceService->getOpenBankingConsentParams();
                        $consentParams = [
                            'purpose' => $params['purpose'],
                            'direct_benefit' => $params['direct_benefit'],
                            'data_requested' => $params['data_requested'],
                            'duration_months' => $params['duration_months'],
                            'agreed_at' => now()->toIso8601String(),
                        ];
                    }
                    $this->complianceService->giveConsent($user, '1.0', $consentParams);
                    $this->conversationManager->setState($from, ConversationManager::STATE_AI_CONVERSATION, []);
                } else {
                    try {
                        $this->whatsappService->sendTextMessage($from, 'Please reply YES to accept our terms and continue using WhatsApp banking.');
                    } catch (\Exception $e) {
                        Log::channel('whatsapp')->warning('Failed to send consent reminder', ['error' => $e->getMessage()]);
                    }
                    return;
                }
            } else {
                $disclosure = $this->complianceService->getDisclosureMessage();
                if ($this->complianceService->isOpenBankingConsentEnabled()) {
                    $params = $this->complianceService->getOpenBankingConsentParams();
                    $disclosure = "Purpose: {$params['purpose']}\nBenefit: {$params['direct_benefit']}\nData we use: {$params['data_requested']}\nDuration: {$params['duration_months']} months.\n" . $disclosure;
                }
                $url = $this->complianceService->getPrivacyPolicyUrl();
                if ($url) {
                    $disclosure .= ' ' . $url;
                }
                try {
                    $this->whatsappService->sendTextMessage($from, $disclosure);
                } catch (\Exception $e) {
                    Log::channel('whatsapp')->warning('Failed to send disclosure', ['error' => $e->getMessage()]);
                }
                $this->conversationManager->setState($from, EscalationService::STATE_AWAITING_CONSENT, []);
                return;
            }
        }

        // ── Prefer human agent: route to support immediately ──
        if ($this->complianceService->preferHumanAgent($user)) {
            app(\App\Services\WhatsApp\WhatsAppMetricsService::class)->incrementHandoffs();
            $ticket = $this->escalationService->createEscalationTicket($user, $from, $sessionData);
            $this->conversationManager->setState($from, EscalationService::STATE_AWAITING_AGENT, []);
            try {
                $this->whatsappService->sendTextMessage($from,
                    "You're connected to our support team. Reference: #{$ticket->ticket_number}. An agent will respond shortly."
                );
            } catch (\Exception $e) {
                Log::channel('whatsapp')->warning('Failed to send prefer-human message', ['error' => $e->getMessage()]);
            }
            return;
        }

        // ── Human handoff: escalation by keyword or YES to offer ──
        if ($this->escalationService->shouldEscalate($user, $from, $state, $sessionData, $userText)) {
            app(\App\Services\WhatsApp\WhatsAppMetricsService::class)->incrementHandoffs();
            $ticket = $this->escalationService->createEscalationTicket($user, $from, $sessionData);
            $this->conversationManager->setState($from, EscalationService::STATE_AWAITING_AGENT, []);
            try {
                $this->whatsappService->sendTextMessage($from,
                    "You've been connected to our support team. Reference: #{$ticket->ticket_number}. An agent will respond shortly. You can also check your ticket in the app."
                );
            } catch (\Exception $e) {
                Log::channel('whatsapp')->warning('Failed to send escalation message', ['error' => $e->getMessage()]);
            }
            Log::channel('whatsapp')->info('Escalation completed', ['user_id' => $user->id, 'ticket' => $ticket->ticket_number]);
            return;
        }

        // ── Session just expired: welcome back and mention pending tasks ──
        if ($wasExpired) {
            $pending = $this->pendingTaskService->getPending($user);
            if ($pending->isNotEmpty()) {
                $list = $pending->take(3)->map(fn ($t) => $t->task_type)->join(', ');
                $count = $pending->count();
                $msg = $count === 1
                    ? "Welcome back. You have an incomplete task: {$list}. Reply 'continue' when ready."
                    : "Welcome back. You have {$count} incomplete task(s): {$list}. Reply 'continue' to resume.";
                try {
                    $this->whatsappService->sendTextMessage($from, $msg);
                } catch (\Exception $e) {
                    Log::channel('whatsapp')->debug('Welcome-back message failed', ['error' => $e->getMessage()]);
                }
            }
        }

        // ── AI Agent (async via queue job) ──
        $aiEnabled = Setting::get('whatsapp_ai_enabled', false);
        if ($aiEnabled) {
            app(\App\Services\WhatsApp\WhatsAppMetricsService::class)->incrementMessagesReceived();
            Log::channel('whatsapp')->info('AI Agent enabled — dispatching to queue', [
                'user_id' => $user->id,
                'phone' => $from,
            ]);

            ProcessAiMessage::dispatch($user->id, $messageData, $state);

            Log::channel('whatsapp')->info('--- MessageHandler::handle() DONE (queued for AI) ---');

            return;
        }

        // AI disabled: send a short reply (no dining flows)
        try {
            $this->whatsappService->sendTextMessage($from, 'Thank you for your message. The assistant is currently being configured. Please try again later or contact support.');
        } catch (\Exception $e) {
            Log::channel('whatsapp')->warning('Failed to send AI-disabled reply', ['error' => $e->getMessage()]);
        }

        Log::channel('whatsapp')->info('--- MessageHandler::handle() DONE (AI disabled) ---');
    }

    /**
     * Check if the sender is over rate limit (per-phone per minute, optional per-user per day).
     */
    protected function isRateLimited(string $phone, int $userId): bool
    {
        if (! Config::get('whatsapp.rate_limit.enabled', true)) {
            return false;
        }

        $minuteKey = 'wa:rate:' . $phone . ':' . now()->format('YmdHi');
        Cache::add($minuteKey, 0, 120);
        $perMinute = (int) Cache::increment($minuteKey);
        $maxPerMinute = (int) Config::get('whatsapp.rate_limit.max_messages_per_minute', 10);
        if ($perMinute > $maxPerMinute) {
            return true;
        }

        $maxPerDay = (int) Config::get('whatsapp.rate_limit.max_messages_per_day_per_user', 0);
        if ($maxPerDay > 0) {
            $dayKey = 'wa:rate:user:' . $userId . ':' . now()->format('Y-m-d');
            Cache::add($dayKey, 0, 86400 * 2);
            $perDay = (int) Cache::increment($dayKey);
            if ($perDay > $maxPerDay) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user by phone number (banking requires registered users)
     */
    protected function getUserByPhone(string $phoneNumber, array $context): ?User
    {
        // Normalize phone number (remove leading + if present for matching)
        $normalized = ltrim($phoneNumber, '+');

        // Try exact match first
        $user = User::where('phone_number', $phoneNumber)
            ->orWhere('phone_number', '+' . $normalized)
            ->orWhere('phone_number', $normalized)
            ->first();

        return $user;
    }

    /**
     * Extract message data based on type
     */
    protected function extractMessageData(array $message, string $type): array
    {
        $data = [
            'type' => $type,
            'message_id' => $message['id'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
        ];

        switch ($type) {
            case 'text':
                $data['text'] = $message['text']['body'] ?? '';
                break;

            case 'interactive':
                if (isset($message['interactive']['type'])) {
                    $interactiveType = $message['interactive']['type'];
                    $data['interactive_type'] = $interactiveType;

                    if ($interactiveType === 'button_reply') {
                        $data['button_id'] = $message['interactive']['button_reply']['id'] ?? null;
                        $data['button_title'] = $message['interactive']['button_reply']['title'] ?? null;
                    } elseif ($interactiveType === 'list_reply') {
                        $data['list_id'] = $message['interactive']['list_reply']['id'] ?? null;
                        $data['list_title'] = $message['interactive']['list_reply']['title'] ?? null;
                    }
                }
                break;

            case 'button':
                $data['text'] = $message['button']['text'] ?? '';
                $data['button_id'] = $message['button']['payload'] ?? $data['text'];
                break;

            case 'image':
            case 'video':
            case 'document':
                $data['media_id'] = $message[$type]['id'] ?? null;
                $data['mime_type'] = $message[$type]['mime_type'] ?? null;
                $data['caption'] = $message[$type]['caption'] ?? null;
                if ($type === 'document' && ! empty($message['document']['filename'])) {
                    $data['filename'] = $message['document']['filename'];
                }
                break;

            case 'location':
                $data['latitude'] = $message['location']['latitude'] ?? null;
                $data['longitude'] = $message['location']['longitude'] ?? null;
                break;

            default:
                Log::info('Unsupported message type', ['type' => $type, 'message' => $message]);
        }

        return $data;
    }
}
