<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppUserMemory;

/**
 * Long-term memory per user (OpenClaw-style MEMORY.md).
 * User is identified by user_id (resolved from phone_number).
 * PIN is used for verification only, not as a storage key.
 *
 * Smart context: getForPrompt() accepts optional maxChars (from config
 * whatsapp.context.max_memory_chars) to truncate for context budget.
 */
class UserMemoryService
{
    /** Max length to keep in memory (trim older content if exceeded). */
    protected int $maxLength = 15000;

    /**
     * Get memory text for inclusion in the AI prompt.
     * If maxChars is set, trims to that length (keeps start; appends "... (truncated)").
     */
    public function getForPrompt(User $user, ?int $maxChars = null): string
    {
        $row = WhatsAppUserMemory::where('user_id', $user->id)->first();

        if (! $row || empty(trim($row->memory_text ?? ''))) {
            return '';
        }

        $text = trim($row->memory_text);
        $limit = $maxChars ?? config('whatsapp.context.max_memory_chars', 8000);
        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit - 18) . "\n... (truncated)";
        }

        return "=== LONG-TERM MEMORY (curated facts about this client) ===\n"
            . $text
            . "\n=== END LONG-TERM MEMORY ===";
    }

    /**
     * Append a line or block to the user's memory (e.g. after a conversation summary).
     */
    public function append(User $user, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $current = $this->get($user);
        $new = $current ? $current . "\n" . $text : $text;
        if (strlen($new) > $this->maxLength) {
            $new = substr($new, -$this->maxLength);
        }
        $this->set($user, $new);
    }

    /**
     * Replace or set the full memory content (use sparingly; prefer append).
     */
    public function set(User $user, string $memoryText): void
    {
        WhatsAppUserMemory::updateOrCreate(
            ['user_id' => $user->id],
            [
                'memory_text' => trim($memoryText) ?: null,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Get raw memory for API/editing.
     */
    public function get(User $user): ?string
    {
        $row = WhatsAppUserMemory::where('user_id', $user->id)->first();

        return $row ? trim($row->memory_text ?? '') ?: null : null;
    }
}
