<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles escalation from AI to human support (handoff).
 * Creates support tickets with context for WhatsApp-originated escalations.
 */
class EscalationService
{
    public const STATE_OFFER_HANDOFF = 'offer_handoff';
    public const STATE_AWAITING_AGENT = 'awaiting_agent';
    public const STATE_AWAITING_CONSENT = 'awaiting_consent';

    protected GuestMemoryService $memoryService;

    public function __construct(GuestMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    /** Escalation keywords (case-insensitive). */
    protected static function escalationKeywords(): array
    {
        return [
            'agent', 'human', 'representative', 'real person', 'speak to someone',
            'support', 'talk to agent', 'customer service', 'complaint', 'fraud',
        ];
    }

    /** Affirmative replies for "connect to agent" offer. */
    protected static function affirmativeWords(): array
    {
        return ['yes', 'yeah', 'yep', 'connect', 'please', 'ok', 'okay', 'sure'];
    }

    /**
     * Determine if we should create a ticket and hand off to a human.
     */
    public function shouldEscalate(User $user, string $phone, string $state, array $sessionData, string $userText): bool
    {
        $text = strtolower(trim($userText));
        if ($text === '') {
            return false;
        }

        // User accepted handoff offer
        if ($state === self::STATE_OFFER_HANDOFF) {
            foreach (self::affirmativeWords() as $word) {
                if ($text === $word || Str::startsWith($text, $word . ' ') || Str::endsWith($text, ' ' . $word)) {
                    return true;
                }
            }
            if (in_array($text, self::affirmativeWords(), true)) {
                return true;
            }
        }

        // Explicit escalation keyword
        foreach (self::escalationKeywords() as $keyword) {
            if (Str::contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a support ticket for escalation and attach conversation context.
     */
    public function createEscalationTicket(User $user, string $phone, array $sessionData): object
    {
        $ticketNumber = 'TKT-' . strtoupper(bin2hex(random_bytes(3)));
        $subject = 'WhatsApp escalation – ' . $user->name . ' (' . $phone . ')';

        $ticketId = DB::table('support_tickets')->insertGetId([
            'user_id' => $user->id,
            'subject' => $subject,
            'status' => 'open',
            'ticket_number' => $ticketNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recentMessages = $this->memoryService->getRecentConversations($user, 10);
        $contextLines = [
            'Escalation from WhatsApp.',
            'Phone: ' . $phone,
            'User ID: ' . $user->id,
            'Recent conversation (last ' . count($recentMessages) . ' messages):',
        ];
        foreach ($recentMessages as $msg) {
            $role = $msg['role'] === 'user' ? 'Client' : 'Assistant';
            $content = isset($msg['content']) ? Str::limit($msg['content'], 200) : '';
            $contextLines[] = "[{$role}] {$content}";
        }
        $body = implode("\n", $contextLines);

        DB::table('support_ticket_messages')->insert([
            'support_ticket_id' => $ticketId,
            'user_id' => $user->id,
            'is_staff' => false,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::channel('whatsapp')->info('Escalation ticket created', [
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'user_id' => $user->id,
        ]);

        return (object) [
            'id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'subject' => $subject,
            'status' => 'open',
        ];
    }
}
