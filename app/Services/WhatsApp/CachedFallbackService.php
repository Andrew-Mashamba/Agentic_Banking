<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * When the AI sidecar is unavailable (circuit open), try to answer common intents
 * using internal API only (no LLM). Returns null if no match.
 */
class CachedFallbackService
{
    protected string $internalBase = 'http://127.0.0.1:8080/api/internal';

    public function tryReply(User $user, array $messageData): ?string
    {
        $text = strtolower(trim($messageData['text'] ?? $messageData['button_title'] ?? $messageData['list_title'] ?? ''));
        if ($text === '') {
            return null;
        }

        $headers = ['X-User-Id' => (string) $user->id];

        // Balance / account summary
        if (preg_match('/\b(balance|balances|account|how much|summary)\b/i', $text)) {
            $res = Http::withHeaders($headers)->get("{$this->internalBase}/accounts/summary");
            if ($res->successful() && isset($res->json()['data'])) {
                $data = $res->json()['data'];
                $accounts = $data['accounts'] ?? [];
                $total = $data['total_balance'] ?? 0;
                $currency = $data['currency'] ?? 'TZS';
                $lines = ["Your total balance: {$currency} " . number_format((float) $total, 2)];
                foreach (array_slice($accounts, 0, 5) as $a) {
                    $mask = '****' . substr($a['account_number'] ?? '', -4);
                    $lines[] = $a['type'] . ' ' . $mask . ': ' . ($a['currency'] ?? $currency) . ' ' . number_format((float) ($a['balance'] ?? 0), 2);
                }
                return implode("\n", $lines);
            }
        }

        // Branches
        if (preg_match('/\b(branch|branches|location|address|where|atm)\b/i', $text)) {
            $res = Http::withHeaders($headers)->get("{$this->internalBase}/branches");
            if ($res->successful() && isset($res->json()['data'])) {
                $branches = $res->json()['data'];
                if (empty($branches)) {
                    return 'No branches on file. Contact support for locations.';
                }
                $lines = ['Our branches:'];
                foreach (array_slice($branches, 0, 5) as $b) {
                    $lines[] = ($b['name'] ?? 'Branch') . ': ' . ($b['address'] ?? '');
                }
                return implode("\n", $lines);
            }
        }

        // Help
        if (preg_match('/\b(help|hi|hello|what can you do)\b/i', $text)) {
            return "I can help with: balance, transfers, cards, loans, branches, and more. The assistant is temporarily limited. For full help, try again in a few minutes or contact support.";
        }

        return null;
    }
}
