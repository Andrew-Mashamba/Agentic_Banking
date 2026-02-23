<?php

namespace App\Jobs;

use App\Models\GuestConversation;
use App\Models\User;
use App\Services\WhatsApp\UserMemoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Daily job: for users with conversation in last 24h, summarize recent messages
 * and append to long-term memory (optional; requires sidecar to respond).
 */
class SummarizeConversationToMemory implements ShouldQueue
{
    use Queueable;

    public function handle(UserMemoryService $memoryService): void
    {
        $userIds = GuestConversation::where('created_at', '>=', now()->subDay())
            ->distinct()
            ->pluck('guest_id');
        $sidecarUrl = 'http://127.0.0.1:8101/ask';
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }
            $rows = GuestConversation::where('guest_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->reverse();
            if ($rows->isEmpty()) {
                continue;
            }
            $text = $rows->map(fn ($r) => ($r->role === 'user' ? 'User: ' : 'Assistant: ') . mb_substr($r->content, 0, 200))->join("\n");
            $systemPrompt = 'Output only 3 bullet points of key facts or preferences for this client\'s long-term memory. No greeting or explanation.';
            try {
                $response = Http::timeout(30)->post($sidecarUrl, [
                    'phone_number' => $user->phone_number ?? (string) $userId,
                    'system_prompt' => $systemPrompt,
                    'prompt' => "Conversation:\n" . $text,
                    'max_chars' => 500,
                ]);
                if ($response->successful() && ($data = $response->json()) && ($data['success'] ?? false) && ! empty($data['data']['answer'] ?? '')) {
                    $summary = trim($data['data']['answer']);
                    if (strlen($summary) > 10) {
                        $memoryService->append($user, 'Summary: ' . $summary);
                        Log::channel('whatsapp')->info('SummarizeConversationToMemory: appended for user', ['user_id' => $userId]);
                    }
                }
            } catch (\Throwable $e) {
                Log::channel('whatsapp')->debug('SummarizeConversationToMemory: skip', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }
}
