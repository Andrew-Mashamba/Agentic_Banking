<?php

namespace App\Services\WhatsApp;

use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppUserPreferences;

/**
 * Consent, retention, opt-out: reads from config with Setting override.
 */
class WhatsAppComplianceService
{
    public function consentRequired(): bool
    {
        return (bool) Setting::get('whatsapp_consent_required', config('whatsapp.compliance.consent_required', true));
    }

    public function getDisclosureMessage(): string
    {
        return (string) Setting::get('whatsapp_disclosure_message', config('whatsapp.compliance.disclosure_message', ''));
    }

    public function getPrivacyPolicyUrl(): string
    {
        return (string) Setting::get('whatsapp_privacy_policy_url', config('whatsapp.compliance.privacy_policy_url', ''));
    }

    public function retentionDaysConversations(): int
    {
        return (int) Setting::get('whatsapp_retention_days_conversations', config('whatsapp.compliance.retention_days_conversations', 365));
    }

    public function retentionDaysPendingTasks(): int
    {
        return (int) Setting::get('whatsapp_pending_task_ttl_days', config('whatsapp.compliance.retention_days_pending_tasks', 90));
    }

    public function retentionDaysAttachments(): int
    {
        return (int) Setting::get('whatsapp_retention_days_attachments', config('whatsapp.compliance.retention_days_attachments', 90));
    }

    public function auditLogRetentionYears(): int
    {
        return (int) Setting::get('whatsapp_audit_retention_years', config('whatsapp.compliance.audit_log_retention_years', 7));
    }

    public function pendingTaskMaxPerUser(): int
    {
        return (int) Setting::get('whatsapp_pending_task_max_per_user', config('whatsapp.compliance.pending_task_max_per_user', 5));
    }

    public function retentionDaysMemory(): int
    {
        return (int) Setting::get('whatsapp_retention_days_memory', config('whatsapp.compliance.retention_days_memory', 365));
    }

    public function getPreferences(User $user): WhatsAppUserPreferences
    {
        return WhatsAppUserPreferences::firstOrCreate(
            ['user_id' => $user->id],
            ['prefer_human_agent' => false, 'disable_long_term_memory' => false]
        );
    }

    public function hasConsented(User $user): bool
    {
        if (! $this->consentRequired()) {
            return true;
        }
        return $this->getPreferences($user)->hasConsented();
    }

    /**
     * @param  array<string, mixed>|null  $consentParameters  Open Banking: purpose, direct_benefit, data_requested, duration_months, agreed_at
     */
    public function giveConsent(User $user, string $version = '1.0', ?array $consentParameters = null): void
    {
        $data = [
            'consent_given_at' => now(),
            'consent_version' => $version,
        ];
        if ($consentParameters !== null) {
            $data['consent_parameters'] = $consentParameters;
        }
        WhatsAppUserPreferences::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );
    }

    public function isOpenBankingConsentEnabled(): bool
    {
        return (bool) Setting::get('whatsapp_open_banking_consent', config('whatsapp.compliance.open_banking_consent_enabled', false));
    }

    /**
     * Five-parameter consent text (Open Banking style) for disclosure.
     *
     * @return array{purpose: string, direct_benefit: string, data_requested: string, duration_months: int}
     */
    public function getOpenBankingConsentParams(): array
    {
        return [
            'purpose' => (string) Setting::get('whatsapp_ob_consent_purpose', config('whatsapp.compliance.open_banking_consent_purpose', '')),
            'direct_benefit' => (string) Setting::get('whatsapp_ob_consent_benefit', config('whatsapp.compliance.open_banking_consent_benefit', '')),
            'data_requested' => (string) Setting::get('whatsapp_ob_consent_data', config('whatsapp.compliance.open_banking_consent_data', '')),
            'duration_months' => (int) Setting::get('whatsapp_ob_consent_duration_months', config('whatsapp.compliance.open_banking_consent_duration_months', 12)),
        ];
    }

    public function getAiTermsDisclaimer(): string
    {
        return (string) Setting::get('whatsapp_ai_terms_disclaimer', config('whatsapp.compliance.ai_terms_disclaimer', ''));
    }

    public function preferHumanAgent(User $user): bool
    {
        return $this->getPreferences($user)->prefer_human_agent;
    }

    public function disableLongTermMemory(User $user): bool
    {
        return $this->getPreferences($user)->disable_long_term_memory;
    }

    public function getPreferredLanguage(User $user): ?string
    {
        $pref = $this->getPreferences($user)->preferred_language;
        return $pref ?: null;
    }

    public function setPreferHumanAgent(User $user, bool $value): void
    {
        $this->getPreferences($user)->update(['prefer_human_agent' => $value]);
    }

    public function setDisableLongTermMemory(User $user, bool $value): void
    {
        $this->getPreferences($user)->update(['disable_long_term_memory' => $value]);
    }

    public function setPreferredLanguage(User $user, ?string $lang): void
    {
        $this->getPreferences($user)->update(['preferred_language' => $lang]);
    }
}
