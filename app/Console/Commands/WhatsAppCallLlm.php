<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WhatsApp\AiAgentService;
use App\Services\WhatsApp\CapturingWhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Call the webhook flow with a real prompt and print the LLM response.
 * Uses CapturingWhatsAppService so the reply is not sent to WhatsApp.
 *
 * Prerequisites:
 * - AI sidecar running (e.g. scripts/ai-assistant.py on port 8101)
 * - Internal API available if sidecar calls it (e.g. php artisan serve --port=8080)
 * - A user with the given phone number (e.g. seed BankingUsersSeeder)
 */
class WhatsAppCallLlm extends Command
{
    protected $signature = 'whatsapp:call-llm
                            {prompt : The user message to send (e.g. "What is the status of my accounts?")}
                            {--phone=255712111001 : Phone number of a registered user}
                            {--short : Use minimal prompt (bypasses full banker context; works with sidecar 32k limit)}';

    protected $description = 'Call the WhatsApp AI flow with a prompt and print the LLM response (no WhatsApp send).';

    public function handle(): int
    {
        $prompt = $this->argument('prompt');
        $phone = $this->option('phone');
        $short = $this->option('short');
        $normalized = ltrim(preg_replace('/\D/', '', $phone), '0');

        $user = User::where('phone_number', $phone)
            ->orWhere('phone_number', '+' . $normalized)
            ->orWhere('phone_number', $normalized)
            ->first();

        if (! $user) {
            $this->error("No user found for phone: {$phone}. Run BankingUsersSeeder or use an existing user's phone.");
            return 1;
        }

        $this->info("User: {$user->name} ({$user->phone_number})");
        $this->line("Prompt: {$prompt}");
        if ($short) {
            $this->line('Mode: short (minimal context, sidecar only)');
        }
        $this->newLine();

        if ($short) {
            return $this->callSidecarShort($phone, $prompt);
        }

        $capturer = new CapturingWhatsAppService;
        app()->instance(\App\Services\WhatsApp\WhatsAppService::class, $capturer);

        $messageData = [
            'type' => 'text',
            'text' => $prompt,
            'message_id' => 'wamid.cmd.' . uniqid(),
            'timestamp' => (string) time(),
        ];

        try {
            $aiService = app(AiAgentService::class);
            $handled = $aiService->processMessage($user, $messageData);

            if (! $handled) {
                $this->warn('AI did not handle the message (sidecar may have returned null, 4xx, or timed out).');
                $this->line('Ensure the AI sidecar is running: python3 scripts/ai-assistant.py (port 8101).');
                $this->line('If the sidecar returns "exceeds ... characters", restart it so it loads the 800k limit (scripts/ai-assistant.py), or use --short.');
                if ($capturer->lastTextMessage !== null) {
                    $this->line('Captured fallback message:');
                    $this->line($capturer->lastTextMessage);
                }
                return $capturer->lastTextMessage !== null ? 0 : 1;
            }

            $this->info('--- LLM response ---');
            $this->line($capturer->lastTextMessage ?? '(no message captured)');
            $this->info('--- end ---');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }

    protected function callSidecarShort(string $phone, string $prompt): int
    {
        $sidecarUrl = config('whatsapp.sidecar_url', 'http://127.0.0.1:8101/ask');
        $body = [
            'phone_number' => $phone,
            'system_prompt' => 'You are a senior private banker. Reply in plain text only, no markdown. Be concise.',
            'prompt' => $prompt,
            'max_chars' => 3800,
        ];

        try {
            $response = Http::timeout(130)->post($sidecarUrl, $body);

            if (! $response->successful()) {
                $this->error('Sidecar returned ' . $response->status() . ': ' . substr($response->body(), 0, 300));
                return 1;
            }

            $data = $response->json();
            if (! ($data['success'] ?? false) || ! isset($data['data']['answer'])) {
                $this->error('Sidecar response missing success or answer.');
                return 1;
            }

            $this->info('--- LLM response ---');
            $this->line(trim($data['data']['answer']));
            $this->info('--- end ---');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
