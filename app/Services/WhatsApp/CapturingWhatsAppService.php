<?php

namespace App\Services\WhatsApp;

/**
 * WhatsApp service that captures the last sent text message instead of sending.
 * Used by whatsapp:call-llm to capture the LLM response.
 */
class CapturingWhatsAppService extends WhatsAppService
{
    public ?string $lastTextMessage = null;

    public function sendTextMessage(string $to, string $message): array
    {
        $this->lastTextMessage = $message;
        return [];
    }

    public function sendTypingIndicator(string $to): void
    {
        // no-op
    }
}
