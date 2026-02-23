# WhatsApp AI Banking: Additional Considerations and Possible Gaps

This document lists items that may have been missed or forgotten in the current design, beyond what is already covered in [whatsapp-ai-gaps-and-improvements.md](whatsapp-ai-gaps-and-improvements.md) and [whatsapp-ai-memory-and-pending-tasks.md](whatsapp-ai-memory-and-pending-tasks.md). Use it as a checklist for compliance, resilience, and product completeness.

**Implementation status (as of Feb 2026):** All items below are implemented unless marked otherwise. This includes: consent/disclosure, five-parameter (Open Banking) consent, terms for AI-driven actions, retention (conversations, pending tasks, attachments, audit logs, long-term memory, whatsapp log channel), data export, right-to-be-forgotten, session expiry message, fallbacks (cached + pending-task mention), pending-task limit and TTL, list/cancel-all pending tasks, attachment linked to task, user preferences (prefer human, disable memory, language), attachment limits and virus scan (ClamAV/optional), PIN reset rule, accessibility/language in banker rule, settings seeding, phone number change migration, metrics and circuit-open alerting, prompt versioning, auto-summarize to long-term memory, and tests (compliance, data export, delete-user, additional implementations, banker rule content).

---

## 1. Consent, Disclosure, and Regulatory

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **In-chat consent / disclosure** | **Implemented** | `WhatsAppComplianceService`: consent required flag, disclosure message, privacy policy URL (config + settings). First-time disclosure sent when consent required and user not consented; state `awaiting_consent`; on YES/agree, `giveConsent()` and continue. |
| **Five-parameter consent (Open Banking style)** | **Implemented** | Config/settings: `open_banking_consent_enabled`, purpose, direct_benefit, data_requested, duration_months. When enabled, disclosure shows all five; on agree, `giveConsent($user, version, $consentParameters)` stores JSON in `whatsapp_user_preferences.consent_parameters`. |
| **Terms for AI-driven actions** | **Implemented** | Config/settings: `ai_terms_disclaimer`. Injected into system prompt; AI instructed to include disclaimer for high-risk actions (transfer, card block, loan application). |

---

## 2. Data Retention, Export, and Right to Be Forgotten

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Retention policy (conversations)** | **Implemented** | `config/whatsapp.php` + settings: `retention_days_conversations` (default 365). Scheduled command `whatsapp:prune-conversations` deletes `guest_conversations` older than that. |
| **Retention policy (long-term memory)** | **Implemented** | `retention_days_memory` (config + settings, default 365). Command `whatsapp:prune-memory` clears `memory_text` for users with `updated_at` older than retention. |
| **Retention policy (pending tasks)** | **Implemented** | `retention_days_pending_tasks` (default 90). Command `whatsapp:prune-pending-tasks` marks old pending tasks as `abandoned`. |
| **Retention policy (attachments)** | **Implemented** | `retention_days_attachments` (default 90). Command `whatsapp:prune-attachments` deletes files under configured storage path older than that. |
| **Retention policy (audit logs)** | **Implemented** | `audit_log_retention_years` (default 7). Command `whatsapp:prune-audit-logs` deletes `banking_audit_logs` older than that. Document in policy; retain only if regulation allows. |
| **Data export (GDPR Art. 20 / portability)** | **Implemented** | Internal endpoint `GET /api/internal/whatsapp-data-export` (header `X-User-Id`) returns conversations, long_term_memory_text, pending_tasks, preferences. `WhatsAppDataExportService::exportForUser()`. |
| **Right to be forgotten / deletion** | **Implemented** | `App\Actions\Jetstream\DeleteUser` deletes `guest_conversations` (guest_id), `whatsapp_user_memory`, `whatsapp_user_preferences`, `whatsapp_pending_tasks` before user delete. `banking_audit_logs` are removed via FK cascade; document if anonymization is required instead. |

---

## 3. Session, Timeout, and Continuity

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Session expiry message** | **Implemented** | When session was expired and reset, `MessageHandler` sends welcome-back message and, if user has pending tasks, lists them and “Reply ‘continue’ to resume.” |
| **Pending tasks vs session** | OK | Pending tasks live in `whatsapp_pending_tasks` and survive session expiry; good for resume. No change needed. |
| **Sensitive-action OTP expiry** | Implemented | `PendingSensitiveAction::isExpired()` and configurable window; good. |

---

## 4. Resilience and Fallbacks

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Circuit breaker** | Implemented | Sidecar failures open circuit; fallback sent. Good. |
| **Cached fallbacks for common intents** | **Implemented** | `CachedFallbackService`: when sidecar unavailable after retries, try rule-based reply for balance, branches, help (internal API); else send “Assistant temporarily unavailable” + pending-task line if any. |
| **Fallback message when pending tasks exist** | **Implemented** | `PendingTaskService::getFallbackMessageForUser()`: base message + “You have N incomplete task(s): … Reply ‘continue’ to resume.” Used on AI failure and when circuit open. |
| **Idempotency / duplicate webhooks** | Implemented | Dedup by `message_id` (10 min TTL) in `MessageHandler`; good. |

---

## 5. Memory and Pending Tasks (Extra)

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Auto-summarize conversation into long-term memory** | **Implemented** | Daily job `SummarizeConversationToMemory`: for users with conversation in last 24h, fetches last 20 messages, calls sidecar with summarize prompt, appends result to `UserMemoryService`. Scheduled at 04:00. |
| **Pending task limit per user** | **Implemented** | `pending_task_max_per_user` (config + settings, default 5). `PendingTaskService::createOrUpdate()`: when creating a new task and at cap, oldest pending task is marked abandoned, then new task is created. |
| **Clear pending task from chat** | **Implemented** | GET /pending-tasks (internal + v1 `GET /whatsapp-pending-tasks`) lists tasks. POST /pending-tasks/cancel-all (internal + v1 `POST /whatsapp-pending-tasks/cancel-all`) marks all abandoned. Banker rule: “list my pending tasks” and “cancel all” use these. |

---

## 6. Localization and Accessibility

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Multi-language** | **Implemented** | `whatsapp_user_preferences.preferred_language` (e.g. en, sw). `AiAgentService` injects “PREFERRED LANGUAGE: … Respond in that language” into user prompt. Banker rule: “When the prompt includes PREFERRED LANGUAGE, respond in that language.” |
| **Accessibility** | **Implemented** | Banker rule: “Use clear, simple language. Avoid jargon where possible; if you must use a banking term, briefly explain it. … Offer to rephrase or simplify if the client seems confused.” |

---

## 7. Security and Identity

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Phone number change** | **Implemented** | `WhatsAppPhoneMigrationService::migrate($user, $oldPhone, $newPhone)` updates `whatsapp_sessions.phone_number` and `whatsapp_pending_tasks.phone_number`, clears cache for old phone. Called from ProfileController when user updates `phone_number`. |
| **Multiple devices / same user** | OK | One session per phone number; one user per phone in our model. No multi-device sync; acceptable for many use cases. |
| **PIN reset via WhatsApp** | **Documented** | Banker rule: “Never offer to reset or change the client’s transaction PIN or login password via WhatsApp. These actions must be done only in the mobile app or at a branch.” AI directed to say security message if asked. |

---

## 8. Observability and Operations

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Metrics** | **Implemented** | `WhatsAppMetricsService`: daily counters (messages, ai_requests, ai_failures, handoffs, circuit_opens) in cache. Incremented from MessageHandler, ProcessAiMessage, escalation, and circuit-open listener. `getTodayCounts()` for monitoring. |
| **Alerting** | **Implemented** | `WhatsAppCircuitOpened` event dispatched when circuit opens; `LogWhatsAppCircuitOpened` listener logs “ALERT: circuit breaker opened” to whatsapp channel and increments circuit_opens metric. |
| **Prompt / rule versioning** | **Implemented** | Config `whatsapp.prompt_version`. Each AI request logs to `whatsapp_request_logs` (user_id, phone, prompt_version, prompt_hash). Version passed to sidecar in request body. |
| **Log retention for whatsapp channel** | **Implemented** | Config `whatsapp.log_retention_days` (default 90). `config/logging.php` whatsapp channel uses `days` from env. Command `whatsapp:prune-logs` deletes `storage/logs/whatsapp*.log` files older than retention. |

---

## 9. Testing and Quality

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Tests for memory and pending tasks** | **Implemented** | `WhatsAppComplianceAndDataExportTest` and `WhatsAppAdditionalImplementationsTest`: compliance, consent (including Open Banking params), data export, delete-user cascade, pending-task list/cancel-all, phone migration, metrics, request log, attachment-to-task, retention/compliance getters, banker rule content. |
| **Tests for flows** | Unknown | Flow definitions and FlowDataHandler are complex; add tests for critical flows (e.g. transfer, loan_application) so regressions are caught. |
| **Regression tests for banker behaviour** | **Partial** | Test asserts banker rule file contains required phrases (list/cancel pending tasks, etc.). Full snapshot or sidecar tests not implemented. |

---

## 10. User Control and Opt-Out

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Opt-out of AI** | **Implemented** | `whatsapp_user_preferences.prefer_human_agent`. When true, `MessageHandler` creates escalation ticket, sets state to `awaiting_agent`, sends support message. |
| **Disable long-term memory** | **Implemented** | `whatsapp_user_preferences.disable_long_term_memory`. When true, `AiAgentService` does not inject long-term memory block into prompt. |
| **Clear my data** | **Implemented** | API: `POST /api/v1/whatsapp-clear-data` (auth:sanctum) clears `guest_conversations` and `whatsapp_user_memory` for the authenticated user. Preferences and pending tasks metadata remain unless user is fully deleted. |

---

## 11. Attachment and Document Handling

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Virus/malware scan** | **Implemented** | `AttachmentScannerInterface`, `NullScanner`, `ClamAvScanner`. Config `whatsapp.attachments.scan_enabled` and `scanner` (null|clamav). After save, if scan enabled and scanner reports infected, file is deleted and null returned. |
| **File type / size limits** | **Implemented** | `config/whatsapp.attachments`: `max_bytes` (default 10 MB), `allowed_mime_types` (images + PDF). `WhatsAppService::downloadAndSaveMedia()` rejects if size &gt; max or mime not in allowlist; does not save. |
| **Attachment linked to task** | **Implemented** | When user sends attachment, `PendingTaskService::appendAttachmentToLatestTask($user, $path, $type, $caption)` adds to latest pending task’s `context.attachments[]` for resume. |

---

## 12. Settings and Configuration

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **Compliance settings in DB** | **Implemented** | `WhatsAppComplianceService` reads from `Setting::get(...)` with config fallbacks. Keys: `whatsapp_consent_required`, `whatsapp_retention_days_conversations`, `whatsapp_pending_task_ttl_days`, `whatsapp_retention_days_attachments`, `whatsapp_audit_retention_years`, `whatsapp_pending_task_max_per_user`. |
| **Settings seeding** | **Implemented** | `SettingsSeeder` seeds default values for the above keys so behaviour is correct before any admin overrides. |

---

## 13. API for Preferences and Export

| Item | Status | Notes / Recommendation |
|------|--------|------------------------|
| **WhatsApp preferences (v1)** | **Implemented** | `GET /api/v1/whatsapp-preferences`, `PUT /api/v1/whatsapp-preferences` (auth:sanctum). Body: `prefer_human_agent`, `disable_long_term_memory`, `preferred_language` (en, sw). |
| **WhatsApp data export** | **Implemented** | Internal: `GET /api/internal/whatsapp-data-export` with `X-User-Id` returns full export (conversations, long_term_memory_text, pending_tasks, preferences). |

---

## 14. Summary Table (Quick Reference)

| Category | Status | Implementation |
|----------|--------|----------------|
| Consent / disclosure | Done | Compliance service, disclosure message, awaiting_consent state, giveConsent(). |
| Data retention policies | Done | Config + settings; commands: prune-conversations, prune-pending-tasks, prune-attachments, prune-audit-logs; scheduled in Kernel. |
| Right to be forgotten / export | Done | DeleteUser cascades to guest_conversations, user_memory, preferences, pending_tasks; internal data export endpoint. |
| Session expiry message | Done | Welcome back + pending tasks when session was expired. |
| Fallback when pending tasks exist | Done | getFallbackMessageForUser() in all failure/unavailable paths. |
| Cached fallbacks | Done | CachedFallbackService for balance, branches, help when circuit open. |
| Pending task TTL / limit | Done | TTL job; max per user with abandon-oldest. |
| Multi-language / accessibility | Done | preferred_language in prompt; banker rule PIN reset + plain language. |
| User opt-out / clear data | Done | prefer_human_agent, disable_long_term_memory; v1 whatsapp-preferences and whatsapp-clear-data. |
| Attachment limits | Done | max_bytes and allowed_mime_types in downloadAndSaveMedia. |
| Metrics and alerting | Optional | Logging only; add counters/alerts if needed. |
| Tests | Recommended | Add tests for compliance, consent, retention, export, delete-user, limits. |

---

This list is a living checklist. Items already implemented in the main gaps doc (webhook verification, rate limiting, handoff, audit, OTP, retry/circuit breaker, flows, memory, pending tasks) are not repeated here.
