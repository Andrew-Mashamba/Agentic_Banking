<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteUser;
use App\Models\GuestConversation;
use App\Models\User;
use App\Models\WhatsAppPendingTask;
use App\Models\WhatsAppUserMemory;
use App\Models\WhatsAppUserPreferences;
use App\Services\WhatsApp\WhatsAppComplianceService;
use App\Services\WhatsApp\WhatsAppDataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppComplianceAndDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_service_uses_config_defaults(): void
    {
        $compliance = app(WhatsAppComplianceService::class);
        $this->assertIsBool($compliance->consentRequired());
        $this->assertGreaterThan(0, $compliance->retentionDaysConversations());
        $this->assertGreaterThan(0, $compliance->pendingTaskMaxPerUser());
    }

    public function test_compliance_preferences_and_consent(): void
    {
        $user = User::factory()->create();
        $compliance = app(WhatsAppComplianceService::class);

        $this->assertFalse($compliance->hasConsented($user));
        $compliance->giveConsent($user, '1.0');
        $this->assertTrue($compliance->hasConsented($user));

        $compliance->setPreferHumanAgent($user, true);
        $this->assertTrue($compliance->preferHumanAgent($user));
        $compliance->setDisableLongTermMemory($user, true);
        $this->assertTrue($compliance->disableLongTermMemory($user));
        $compliance->setPreferredLanguage($user, 'sw');
        $this->assertSame('sw', $compliance->getPreferredLanguage($user));
    }

    public function test_data_export_returns_user_data(): void
    {
        $user = User::factory()->create();
        GuestConversation::create(['guest_id' => $user->id, 'role' => 'user', 'content' => 'Hi', 'message_type' => 'text']);
        GuestConversation::create(['guest_id' => $user->id, 'role' => 'assistant', 'content' => 'Hello', 'message_type' => 'text']);
        WhatsAppUserMemory::create(['user_id' => $user->id, 'memory_text' => 'Prefers TZS', 'updated_at' => now()]);
        WhatsAppPendingTask::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number ?? '',
            'task_type' => 'loan_application',
            'status' => 'pending',
        ]);

        $export = app(WhatsAppDataExportService::class);
        $data = $export->exportForUser($user);

        $this->assertArrayHasKey('exported_at', $data);
        $this->assertSame($user->id, $data['user_id']);
        $this->assertCount(2, $data['conversations']);
        $this->assertStringContainsString('Prefers TZS', $data['long_term_memory_text']);
        $this->assertCount(1, $data['pending_tasks']);
        $this->assertArrayHasKey('preferences', $data);
    }

    public function test_internal_whatsapp_data_export_endpoint(): void
    {
        $user = User::factory()->create();
        $res = $this->getJson('/api/internal/whatsapp-data-export', ['X-User-Id' => (string) $user->id]);
        $res->assertStatus(200)->assertJsonPath('data.user_id', $user->id);
        $this->getJson('/api/internal/whatsapp-data-export')->assertStatus(400);
        $this->getJson('/api/internal/whatsapp-data-export', ['X-User-Id' => '99999'])->assertStatus(404);
    }

    public function test_v1_whatsapp_preferences_and_clear_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/whatsapp-preferences')->assertStatus(200);
        $this->putJson('/api/v1/whatsapp-preferences', [
            'prefer_human_agent' => true,
            'preferred_language' => 'en',
        ])->assertStatus(200);
        $prefs = WhatsAppUserPreferences::where('user_id', $user->id)->first();
        $this->assertTrue($prefs->prefer_human_agent);
        $this->assertSame('en', $prefs->preferred_language);

        GuestConversation::create(['guest_id' => $user->id, 'role' => 'user', 'content' => 'Test', 'message_type' => 'text']);
        WhatsAppUserMemory::create(['user_id' => $user->id, 'memory_text' => 'Memo', 'updated_at' => now()]);
        $this->postJson('/api/v1/whatsapp-clear-data')->assertStatus(200);
        $this->assertSame(0, GuestConversation::where('guest_id', $user->id)->count());
        $this->assertNull(WhatsAppUserMemory::where('user_id', $user->id)->first());
    }

    public function test_delete_user_removes_whatsapp_data(): void
    {
        $user = User::factory()->create();
        GuestConversation::create(['guest_id' => $user->id, 'role' => 'user', 'content' => 'Hi', 'message_type' => 'text']);
        WhatsAppUserMemory::create(['user_id' => $user->id, 'memory_text' => 'X', 'updated_at' => now()]);
        WhatsAppUserPreferences::firstOrCreate(['user_id' => $user->id], ['prefer_human_agent' => false]);
        WhatsAppPendingTask::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number ?? '',
            'task_type' => 'kyc',
            'status' => 'pending',
        ]);

        $userId = $user->id;
        app(DeleteUser::class)->delete($user);

        $this->assertSame(0, GuestConversation::where('guest_id', $userId)->count());
        $this->assertSame(0, WhatsAppUserMemory::where('user_id', $userId)->count());
        $this->assertSame(0, WhatsAppUserPreferences::where('user_id', $userId)->count());
        $this->assertSame(0, WhatsAppPendingTask::where('user_id', $userId)->count());
    }
}
