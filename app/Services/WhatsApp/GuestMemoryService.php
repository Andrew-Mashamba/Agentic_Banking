<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\GuestConversation;
use Illuminate\Support\Facades\Log;

/**
 * Manages long-term memory for WhatsApp AI Banking.
 *
 * Three tiers:
 *   Tier 1: Core Profile — users table fields + preferences JSON
 *   Tier 2: Conversation Log — guest_conversations table (recent history)
 *   Tier 3: Banking Products — accounts, cards, loans (queried by AiAgentService)
 *
 * Smart context: max_recent_turns and max_conversation_turn_chars from config.
 */
class GuestMemoryService
{
    /**
     * Build the full memory context block for the AI prompt.
     * Uses config whatsapp.context.max_recent_turns and max_conversation_turn_chars.
     */
    public function buildMemoryContext(User $user, ?int $maxTurns = null): string
    {
        $sections = [];

        // Tier 1: Client profile
        $profile = $this->getProfile($user);
        if (! empty($profile)) {
            $sections[] = $this->formatProfile($user, $profile);
        } else {
            $sections[] = $this->formatNewClient($user);
        }

        // Tier 2: Recent conversation history (capped by smart context config)
        $limit = $maxTurns ?? config('whatsapp.context.max_recent_turns', 20);
        $recentMessages = $this->getRecentConversations($user, $limit);
        if (! empty($recentMessages)) {
            $sections[] = $this->formatConversationHistory($recentMessages);
        }

        return implode("\n\n", $sections);
    }

    /**
     * Save a conversation turn (user message + AI response).
     */
    public function saveConversationTurn(User $user, string $userMessage, string $aiResponse, string $messageType = 'text'): void
    {
        // Store using user_id in guest_id column (table reuse)
        GuestConversation::create([
            'guest_id' => $user->id,
            'role' => 'user',
            'content' => $userMessage,
            'message_type' => $messageType,
        ]);

        $storedResponse = mb_strlen($aiResponse) > 1000
            ? mb_substr($aiResponse, 0, 997) . '...'
            : $aiResponse;

        GuestConversation::create([
            'guest_id' => $user->id,
            'role' => 'assistant',
            'content' => $storedResponse,
            'message_type' => $messageType,
        ]);
    }

    /**
     * Extract facts from a conversation and update the user profile.
     */
    public function updateProfileFromConversation(User $user, string $userMessage, string $aiResponse): void
    {
        // Banking doesn't need the same profile extraction as restaurant
        // Profile data comes from KYC and account info
    }

    /**
     * Refresh profile from transaction history (stub).
     */
    public function refreshProfileFromOrderHistory(User $user): void
    {
        // No-op in banking context
    }

    /**
     * Get the user's profile preferences.
     */
    public function getProfile(User $user): array
    {
        return $user->preferences ?? [];
    }

    /**
     * Get recent conversation messages for a user.
     */
    public function getRecentConversations(User $user, ?int $limit = null): array
    {
        $limit = $limit ?? config('whatsapp.context.max_recent_turns', 20);

        return GuestConversation::where('guest_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['role', 'content', 'created_at'])
            ->reverse()
            ->values()
            ->toArray();
    }

    /**
     * Format the client profile for inclusion in the AI prompt.
     */
    protected function formatProfile(User $user, array $profile): string
    {
        $lines = ["=== CLIENT PROFILE ==="];
        $lines[] = "Name: {$user->name}";
        $lines[] = "Email: {$user->email}";
        $lines[] = "Phone: {$user->phone_number}";
        $lines[] = "Status: {$user->status}";

        if ($user->created_at) {
            $lines[] = "Client since: " . $user->created_at->format('M j, Y');
        }

        if (isset($profile['last_interaction'])) {
            $lines[] = "Last interaction: " . \Carbon\Carbon::parse($profile['last_interaction'])->diffForHumans();
        }

        if (isset($profile['preferred_language'])) {
            $lines[] = "Preferred language: {$profile['preferred_language']}";
        }

        if (isset($profile['communication_preference'])) {
            $lines[] = "Communication preference: {$profile['communication_preference']}";
        }

        $lines[] = "=== END CLIENT PROFILE ===";

        return implode("\n", $lines);
    }

    /**
     * Format context for a new client.
     */
    protected function formatNewClient(User $user): string
    {
        $name = $user->name ?? 'Client';

        return "=== CLIENT PROFILE ===\n"
            . "This is a NEW client. Name: {$name}.\n"
            . "No previous WhatsApp interactions on file.\n"
            . "Be extra welcoming and professional.\n"
            . "=== END CLIENT PROFILE ===";
    }

    /**
     * Format recent conversation history for the prompt.
     */
    protected function formatConversationHistory(array $messages): string
    {
        if (empty($messages)) {
            return '';
        }

        $lines = ["=== RECENT CONVERSATION HISTORY ==="];

        $maxTurnChars = config('whatsapp.context.max_conversation_turn_chars', 300);
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? 'Client' : 'You';
            $time = \Carbon\Carbon::parse($msg['created_at'])->diffForHumans();
            $content = $msg['content'];
            if (mb_strlen($content) > $maxTurnChars) {
                $content = mb_substr($content, 0, $maxTurnChars - 3) . '...';
            }
            $lines[] = "[{$time}] {$role}: {$content}";
        }

        $lines[] = "=== END CONVERSATION HISTORY ===";

        return implode("\n", $lines);
    }

    /**
     * Check if the profile should be refreshed.
     */
    public function shouldRefreshProfile(User $user): bool
    {
        return false; // No profile refresh needed in banking context
    }
}
