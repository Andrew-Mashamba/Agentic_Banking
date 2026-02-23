<?php

namespace Tests\Feature;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Test calling POST /api/webhooks/whatsapp with different prompts.
 * Uses a seeded banking user so MessageHandler resolves the user and processes the message.
 */
class WhatsAppWebhookPromptsTest extends TestCase
{
    use RefreshDatabase;

    /** @var string Phone number of a registered banking user (must exist in DB). */
    protected string $userPhone = '255712111001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BankingUsersSeeder::class);
        Config::set('whatsapp.verify_signature', false);
        Config::set('whatsapp.webhook_secret', 'test_verify_token');

        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendTextMessage')->andReturn([]);
        });
    }

    protected function webhookPayload(string $text, string $messageId = null): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550000000',
                                    'phone_number_id' => '123',
                                ],
                                'contacts' => [
                                    ['wa_id' => $this->userPhone, 'profile' => ['name' => 'Test User']],
                                ],
                                'messages' => [
                                    [
                                        'id' => $messageId ?? 'wamid.' . uniqid(),
                                        'from' => $this->userPhone,
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => $text],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_webhook_verify_returns_challenge_when_token_matches(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_verify_token',
            'hub_challenge' => 'challenge_123',
        ]));
        $response->assertStatus(200);
        $this->assertSame('challenge_123', $response->getContent());
    }

    protected function enableAiAndDisableConsent(): void
    {
        \App\Models\Setting::set('whatsapp_ai_enabled', '1', 'boolean');
        \App\Models\Setting::set('whatsapp_consent_required', '0', 'boolean');
        \Illuminate\Support\Facades\Cache::flush();
    }

    /** @dataProvider textPromptProvider */
    public function test_webhook_accepts_text_prompts(string $prompt): void
    {
        $this->enableAiAndDisableConsent();

        $response = $this->postJson('/api/webhooks/whatsapp', $this->webhookPayload($prompt));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public static function textPromptProvider(): array
    {
        return [
            'balance' => ['What is my balance?'],
            'help' => ['Help'],
            'list pending tasks' => ['List my pending tasks'],
            'branches' => ['Where are your branches?'],
            'transfer' => ['I want to transfer 100 to John'],
            'hi' => ['Hi'],
            'account status' => ['What is the status of my accounts?'],
        ];
    }

    public function test_webhook_returns_200_for_unknown_phone_without_crashing(): void
    {
        $payload = $this->webhookPayload('Hello');
        $payload['entry'][0]['changes'][0]['value']['messages'][0]['from'] = '255999999999';
        $payload['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id'] = '255999999999';

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_webhook_returns_200_for_empty_entry(): void
    {
        $response = $this->postJson('/api/webhooks/whatsapp', ['entry' => []]);
        $response->assertStatus(200);
    }
}
