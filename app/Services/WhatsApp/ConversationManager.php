<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;

class ConversationManager
{
    // Banking context: single AI conversation state
    const STATE_AI_CONVERSATION = 'AI_CONVERSATION';

    protected StateManager $stateManager;

    public function __construct(StateManager $stateManager)
    {
        $this->stateManager = $stateManager;
    }

    /**
     * Get or create a session for a phone number.
     * Auto-resets expired sessions.
     */
    public function getSession(string $phoneNumber): WhatsAppSession
    {
        $session = WhatsAppSession::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['state' => self::STATE_AI_CONVERSATION, 'last_activity_at' => now()]
        );

        $wasExpired = $session->isExpired();
        if ($wasExpired) {
            $session->update([
                'state' => self::STATE_AI_CONVERSATION,
                'data' => null,
                'last_activity_at' => now(),
            ]);
            $this->stateManager->clearStateByPhone($phoneNumber);
        }
        $session->wasExpired = $wasExpired;

        return $session;
    }

    /**
     * Get the current conversation state (cache-first, then DB fallback).
     */
    public function getState(string $phoneNumber): string
    {
        // Try cache first for performance
        $cachedState = $this->stateManager->getStateByPhone($phoneNumber);
        if ($cachedState) {
            return $cachedState;
        }

        // Fall back to DB and sync cache
        $session = $this->getSession($phoneNumber);
        $state = $session->state ?? self::STATE_AI_CONVERSATION;
        $this->stateManager->setStateByPhone($phoneNumber, $state);

        return $state;
    }

    /**
     * Set conversation state and optionally merge data.
     */
    public function setState(string $phoneNumber, string $state, array $data = []): void
    {
        $session = $this->getSession($phoneNumber);

        $updateData = [
            'state' => $state,
            'last_activity_at' => now(),
        ];

        if (! empty($data)) {
            $updateData['data'] = array_merge($session->data ?? [], $data);
        }

        $session->update($updateData);

        // Sync to cache for fast reads
        $this->stateManager->setStateByPhone($phoneNumber, $state);
    }

    /**
     * Get all session data.
     */
    public function getSessionData(string $phoneNumber): array
    {
        return $this->getSession($phoneNumber)->data ?? [];
    }

    /**
     * Update a specific key in session data.
     */
    public function updateSessionData(string $phoneNumber, string $key, mixed $value): void
    {
        $session = $this->getSession($phoneNumber);
        $data = $session->data ?? [];
        $data[$key] = $value;

        $session->update([
            'data' => $data,
            'last_activity_at' => now(),
        ]);

        $this->stateManager->updateContextByPhone($phoneNumber, $key, $value);
    }

    /**
     * Link a user to this session (uses guest_id column for compatibility).
     */
    public function linkGuest(string $phoneNumber, int $userId): void
    {
        $this->getSession($phoneNumber)->update([
            'guest_id' => $userId,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Clear session state and data.
     */
    public function clearSession(string $phoneNumber): void
    {
        $session = $this->getSession($phoneNumber);

        $session->update([
            'state' => self::STATE_AI_CONVERSATION,
            'data' => null,
            'last_activity_at' => now(),
        ]);

        $this->stateManager->clearStateByPhone($phoneNumber);
    }

    /**
     * Check if a session has expired.
     */
    public function isSessionExpired(string $phoneNumber): bool
    {
        $session = WhatsAppSession::where('phone_number', $phoneNumber)->first();

        if (! $session) {
            return true;
        }

        return $session->isExpired();
    }
}
