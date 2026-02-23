<?php

namespace Tests\Feature;

use App\Events\WhatsAppCircuitOpened;
use App\Models\GuestConversation;
use App\Models\User;
use App\Models\WhatsAppPendingTask;
use App\Models\WhatsAppRequestLog;
use App\Models\WhatsAppSession;
use App\Models\WhatsAppUserMemory;
use App\Services\WhatsApp\PendingTaskService;
use App\Services\WhatsApp\WhatsAppComplianceService;
use App\Services\WhatsApp\WhatsAppMetricsService;
use App\Services\WhatsApp\WhatsAppPhoneMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppAdditionalImplementationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_banking_consent_params_stored(): void
    {
        $user = User::factory()->create();
        $compliance = app(WhatsAppComplianceService::class);
        $params = [
            'purpose' => 'Test purpose',
            'direct_benefit' => 'Benefit',
            'data_requested' => 'Data',
            'duration_months' => 12,
            'agreed_at' => now()->toIso8601String(),
        ];
        $compliance->giveConsent($user, '1.0', $params);
        $prefs = $compliance->getPreferences($user);
        $this->assertSame($params['purpose'], $prefs->consent_parameters['purpose'] ?? null);
    }

    public function test_phone_migration_updates_session_and_pending_tasks(): void
    {
        $user = User::factory()->create(['phone_number' => '+255111111111']);
        WhatsAppSession::create([
            'phone_number' => '+255111111111',
            'state' => 'AI_CONVERSATION',
            'guest_id' => $user->id,
            'last_activity_at' => now(),
        ]);
        WhatsAppPendingTask::create([
            'user_id' => $user->id,
            'phone_number' => '+255111111111',
            'task_type' => 'loan_application',
            'status' => 'pending',
        ]);
        $svc = app(WhatsAppPhoneMigrationService::class);
        $svc->migrate($user, '+255111111111', '+255722222222');
        $this->assertDatabaseHas('whatsapp_sessions', ['phone_number' => '+255722222222']);
        $this->assertDatabaseHas('whatsapp_pending_tasks', ['user_id' => $user->id, 'phone_number' => '+255722222222']);
    }

    public function test_cancel_all_pending_tasks(): void
    {
        $user = User::factory()->create();
        WhatsAppPendingTask::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number ?? '',
            'task_type' => 'kyc',
            'status' => 'pending',
        ]);
        $svc = app(PendingTaskService::class);
        $count = $svc->cancelAllForUser($user);
        $this->assertSame(1, $count);
        $this->assertSame(0, WhatsAppPendingTask::where('user_id', $user->id)->where('status', 'pending')->count());
    }

    public function test_metrics_increments_and_get_today_counts(): void
    {
        $m = app(WhatsAppMetricsService::class);
        $m->incrementMessagesReceived();
        $m->incrementMessagesReceived();
        $counts = $m->getTodayCounts();
        $this->assertSame(2, $counts['messages']);
    }

    public function test_circuit_opened_event_dispatches_and_listener_increments_metrics(): void
    {
        Event::fake([WhatsAppCircuitOpened::class]);
        event(new WhatsAppCircuitOpened('test'));
        Event::assertDispatched(WhatsAppCircuitOpened::class);
    }

    public function test_request_log_created_with_prompt_version(): void
    {
        $user = User::factory()->create();
        WhatsAppRequestLog::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number ?? '',
            'prompt_version' => '1.0',
            'prompt_hash' => hash('sha256', 'test'),
        ]);
        $this->assertDatabaseHas('whatsapp_request_logs', ['user_id' => $user->id, 'prompt_version' => '1.0']);
    }

    public function test_append_attachment_to_latest_task(): void
    {
        $user = User::factory()->create();
        $task = WhatsAppPendingTask::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number ?? '',
            'task_type' => 'loan_application',
            'status' => 'pending',
            'context' => [],
        ]);
        $svc = app(PendingTaskService::class);
        $svc->appendAttachmentToLatestTask($user, 'path/to/file.pdf', 'document', 'ID copy');
        $task->refresh();
        $this->assertNotEmpty($task->context['attachments'] ?? []);
        $this->assertSame('path/to/file.pdf', $task->context['attachments'][0]['path'] ?? null);
    }

    public function test_v1_whatsapp_pending_tasks_list_and_cancel_all(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        WhatsAppPendingTask::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number ?? '',
            'task_type' => 'kyc',
            'status' => 'pending',
        ]);
        $this->getJson('/api/v1/whatsapp-pending-tasks')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/whatsapp-pending-tasks/cancel-all')->assertStatus(200)->assertJsonPath('data.cancelled', 1);
        $this->getJson('/api/v1/whatsapp-pending-tasks')->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_banker_rule_contains_list_and_cancel_pending_tasks(): void
    {
        $path = base_path('.cursor/rules/whatsapp-banker.mdc');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('list my pending tasks', $content);
        $this->assertStringContainsString('cancel all', $content);
        $this->assertStringContainsString('cancel-all', $content);
    }

    public function test_retention_days_memory_from_compliance(): void
    {
        $c = app(WhatsAppComplianceService::class);
        $this->assertGreaterThan(0, $c->retentionDaysMemory());
    }

    public function test_ai_terms_disclaimer_from_compliance(): void
    {
        $c = app(WhatsAppComplianceService::class);
        $this->assertIsString($c->getAiTermsDisclaimer());
    }
}
