# WhatsApp AI Banking: Gaps and Improvements

This document summarizes research on similar systems (WhatsApp banking chatbots, conversational banking AI, security/compliance) and maps findings to gaps and concrete improvements for our implementation.

---

## 1. Research Summary: Similar Systems

### 1.1 Industry patterns

| Area | What similar systems do |
|------|-------------------------|
| **Security** | OTP/2FA before sensitive actions; webhook HMAC-SHA256 verification; session timeout + explicit logout; masked data in chat; KYC-based limits. |
| **Handoff** | Policy-aware escalation to humans (identity uncertainty, fraud, complex servicing, distressed users, regulatory disclosures); full context passed to agents; smart routing by issue type. |
| **Rate limiting** | Per-user and/or per-phone limits (e.g. messages/minute, messages/day); token/request limits on AI calls; 429 + Retry-After. |
| **Audit / compliance** | Conversation logs with timestamps and user IDs; access logs; immutable/tamper-evident trails; chain-of-thought or decision rationale; versioned financial logic. |
| **Reliability** | Exponential backoff + jitter for retries; circuit breakers when sidecar is down; fallbacks (e.g. cached reply or “try later”); timeouts on AI calls. |
| **UX / flows** | WhatsApp Flows for guided linear tasks (e.g. loan application); conversational AI for open-ended queries; 5‑minute target for task completion; one primary task per “screen.” |

### 1.2 References (high level)

- Meta: WhatsApp webhooks (signature verification, HTTPS), Flows best practices (latency, caching, token expiry).
- Banking chatbots: OTP/2FA for transactions; session management; KYC and transaction limits; secure WhatsApp journey (e.g. BankBuddy).
- Handoff: Policy-aware escalation, context preservation, compliance (FDCPA, Reg E, etc.) – e.g. Seiright, IBM Watsonx, Intercom Fin.
- Rate limiting: Multi-dimensional (RPM/TPM/RPD); per-user daily caps; token cost awareness – e.g. Bankr, Azure, TrackAI.
- Audit: Conversation + access + admin + error logs; sidecar guardrails; human-in-the-loop for sensitive actions; explainable/verifiable logic – e.g. ChatNexus, Chatfin, FINOS.
- Reliability: Retries with backoff/jitter; circuit breakers; fallbacks – e.g. Athenic, Portkey, Agent Factory.

---

## 2. Gaps in Our System

### 2.1 Webhook security

- **Gap:** `WebhookController::verifySignature()` exists and uses `X-Hub-Signature-256` + App Secret but is **never called** in `handle()`. All incoming POSTs are processed without verifying they come from Meta.
- **Risk:** Forged or replayed webhook payloads could trigger message handling and AI processing.

### 2.2 Rate limiting

- **Gap:** `config/whatsapp.php` defines `rate_limit` (e.g. `max_messages_per_minute`) but **no code** in `MessageHandler` or `WebhookController` enforces it. No per-phone or per-user throttle before dispatching `ProcessAiMessage`.
- **Risk:** Abuse (flooding), cost spikes (AI/subprocess per message), and potential WhatsApp or internal API throttling.

### 2.3 Human handoff / escalation

- **Gap:** No escalation path from AI to a human agent. When the AI fails or the user is stuck, the only option is a generic “Sorry, try again or contact support” with no ticket, no context transfer, and no routing.
- **Risk:** Poor experience for complex or emotional cases; compliance exposure where human intervention or documentation is required.

### 2.4 Audit and compliance logging

- **Gap:** WhatsApp/AI flow uses `whatsapp` log channel and `GuestConversation` for text history, but:
  - No structured **audit trail** (who did what, when, from which session) for banking actions triggered via WhatsApp (e.g. transfer, freeze card, loan application).
  - No **tamper-evident** or immutable log; no dedicated “AI decision + rationale” record for examiners.
- **Risk:** Harder to satisfy regulatory and internal audit requirements for AI-driven banking actions.

### 2.5 Transaction / sensitive-action authentication

- **Gap:** No extra authentication step (e.g. OTP, PIN, or in-app confirmation) for high-risk actions (transfers, card block, loan application) initiated via WhatsApp. The AI relies on “reply YES” and session identity (phone → user).
- **Risk:** If the WhatsApp session is compromised, an attacker could confirm harmful transactions without a second factor.

### 2.6 Retries and circuit breaker

- **Gap:** `ProcessAiMessage` has `tries = 2` and `backoff = 5` (fixed). There is no:
  - **Exponential backoff** or jitter.
  - **Circuit breaker** when the sidecar repeatedly fails (e.g. 503/504); jobs keep failing and retrying, potentially worsening load.
  - **Differentiation** between retryable (5xx, 429, timeout) and non-retryable (4xx) errors.
- **Risk:** Unnecessary load on the sidecar during outages; slower recovery; no clear “degraded mode” for users.

### 2.7 Webhook payload logging

- **Gap:** Full raw body and parsed payload are logged in `handle()`. This may include user-generated text and metadata.
- **Risk:** Logs become a data store of PII/sensitive content; retention and access control for these logs are critical and may not be defined.

---

## 3. Recommended Improvements

### 3.1 Enable webhook signature verification (high priority)

- In `WebhookController::handle()`, **before** processing `$data`, call `$this->verifySignature($request)` (or equivalent). On failure, return 403 and do not process the body.
- Use the same App Secret as in verification (e.g. `whatsapp_webhook_secret` or Meta App Secret); ensure it is not logged.
- Optionally support a feature flag to disable verification in development if needed (e.g. ngrok without Meta signing).

### 3.2 Enforce rate limiting (high priority)

- **Per-phone:** Before dispatching `ProcessAiMessage`, check a counter (e.g. Redis or Cache) keyed by phone (e.g. `wa:rate:{phone}`) with a 1‑minute (or 60-second) window. If over `config('whatsapp.rate_limit.max_messages_per_minute', 10)`, skip dispatch and optionally send one “Too many messages, please wait a moment” reply.
- **Per-user (optional):** Add a daily (or rolling) cap per user to align with “conversational banking” best practices and control AI cost.
- Log rate-limit hits for monitoring and tuning.

### 3.3 Add human handoff / escalation (medium priority)

- **Trigger options:** Keyword (e.g. “agent”, “human”), intent (e.g. “complaint”, “fraud”), repeated failures, or low AI confidence if exposed by the sidecar.
- **Flow:** Set conversation state to “awaiting_agent” (or similar); create a support ticket (reuse or extend `support_tickets`) with conversation summary and last N messages; notify support (e.g. dashboard or email); send user a message with ticket ref and expected response time.
- **Context:** Attach user id, phone, session id, and recent conversation (or link to stored history) so agents have “notes in hand.”

### 3.4 Audit trail for AI-driven banking actions (medium priority)

- **Scope:** Any action that moves money or changes sensitive state (transfer, card freeze/block, loan application, beneficiary add, etc.), whether triggered via API from the AI or from the app.
- **Record:** User id, action type, resource id, timestamp, channel (e.g. `whatsapp`), session/request id, and optionally a short “reason” or reference (e.g. “client confirmed in chat”).
- **Storage:** Dedicated table or append-only log (e.g. `banking_audit_log` or `ai_action_log`); avoid overwriting. Optionally hash or sign entries for tamper evidence later.
- **AI-specific:** If the agent exposes “reasoning” or tool calls, consider logging a sanitized version (no PII) for explainability.

### 3.5 Stronger authentication for sensitive actions (medium priority)

- For high-risk actions (e.g. transfer above threshold, card block, loan application):
  - Require a second step: OTP sent to registered phone/email, or in-app approval, or transaction PIN if implemented.
  - In WhatsApp, send “To confirm this transfer, enter the 6-digit code we just sent to your phone” and validate before calling the internal API.
- Document which actions are “high risk” and which use only in-chat confirmation.

### 3.6 Retry and circuit breaker (medium priority)

- **Job (`ProcessAiMessage`):**
  - Use exponential backoff (e.g. 5s, 15s, 45s) with jitter instead of fixed `backoff = 5`.
  - In `handle()`, catch sidecar 4xx and do not retry (or only retry for 429 with Retry-After); retry on 5xx/timeout.
- **Circuit breaker (app or job):**
  - Maintain a failure count or failure rate for `http://127.0.0.1:8101` (e.g. in Redis). After N consecutive failures (or M failures in a window), open the circuit: skip calling the sidecar and send a static “Assistant temporarily unavailable; please try again in a few minutes.”
  - After a cooldown (e.g. 60s), allow one test request; if it succeeds, close the circuit.
- **Sidecar:** Optionally respect `Retry-After` on 429 and surface it (e.g. in response body) so the job can back off accordingly.

### 3.7 Safer webhook logging (low priority)

- **Reduce PII in logs:** Do not log full `raw_body` or full `data` in production; log only message id, type, timestamp, and length. Keep detailed logging behind a debug flag or separate channel with restricted access and retention.
- **Policy:** Define retention and access for the `whatsapp` log channel and any logs containing message content.

### 3.8 Optional: WhatsApp Flows for high-value linear tasks (low priority)

- For flows like “apply for loan” or “schedule bulk payment,” consider WhatsApp Flows (structured screens) in addition to free-form chat. Use Flows for the form-like steps and reserve the AI for questions and exceptions.
- Improves completion rates and can reduce ambiguity (e.g. exact amount, term) before hitting the internal API.

---

## 4. Priority Overview

| Priority   | Item                              | Effort (rough) |
|-----------|------------------------------------|----------------|
| High      | Webhook signature verification    | Small          |
| High      | Per-phone rate limiting           | Small          |
| Medium    | Human handoff / escalation       | Medium         |
| Medium    | Audit trail for AI actions        | Medium         |
| Medium    | Stronger auth for sensitive ops   | Medium         |
| Medium    | Retry + circuit breaker           | Small–medium   |
| Low       | Safer webhook logging             | Small          |
| Low       | WhatsApp Flows for key journeys  | Large          |

---

## 5. Conclusion

Our WhatsApp AI banking stack is strong on context (session, memory, pre-fetched data), persona (rules in sidecar), and internal API design. The main gaps are **security** (webhook verification, rate limiting, optional 2FA for sensitive actions), **operational resilience** (retries, circuit breaker), **compliance** (audit trail, handoff), and **observability** (safer logging). Addressing the high-priority items first will align the system with industry practice and reduce risk without changing the core architecture.

---

## 6. Implementation Summary (Done)

| Item | Implementation |
|------|----------------|
| **Webhook signature** | `WebhookController::handle()` calls `verifySignature()` when `whatsapp.verify_signature` is true. Use `WHATSAPP_APP_SECRET` (or `WHATSAPP_VERIFY_TOKEN`) for HMAC. Set `WHATSAPP_VERIFY_SIGNATURE=false` in local dev to disable. |
| **Safer logging** | When `whatsapp.log_verbose` is false (default), only message id, type, from, body_length are logged. Set `WHATSAPP_LOG_VERBOSE=true` for full payloads. |
| **Rate limiting** | `MessageHandler::isRateLimited()` enforces per-phone per-minute and optional per-user per-day. Config: `whatsapp.rate_limit.enabled`, `max_messages_per_minute`, `max_messages_per_day_per_user` (0 = no daily cap). |
| **Human handoff** | `EscalationService`: keyword triggers (agent, human, support, etc.) or "YES" after offer. Consecutive AI failures (≥2) trigger "Would you like to speak to an agent?"; YES creates a support ticket with conversation context. States: `offer_handoff`, `awaiting_agent`. |
| **Audit trail** | `banking_audit_logs` table + `BankingAuditService::log()`. Internal API sets `audit_channel=whatsapp`. Logging added for: transfer, beneficiary_add, card_freeze/unfreeze/block, loan_application, cardless_withdrawal. |
| **Sensitive-action OTP** | `pending_sensitive_actions` table, `SensitiveActionService`, internal routes `POST /api/internal/sensitive-action-request` and `sensitive-action-confirm`. Actions: transfer, card_block, loan_application. OTP sent via WhatsApp; confirm executes the action and logs to audit. |
| **Retry + circuit breaker** | `ProcessAiMessage`: 3 tries, exponential backoff with jitter (5–7s, 15–20s, 45–55s). `SidecarUnavailableException` on 5xx/429/timeout/circuit open; 4xx not retried. Circuit: 5 failures in 60s open the circuit for 60s; success clears failure count. On final failure, user gets "Assistant temporarily unavailable." |

---

## 7. Verification Checklist (implementation vs doc)

| § | Improvement | Status | Notes |
|---|-------------|--------|-------|
| **3.1** | Webhook signature verification | **Done** | `verifySignature()` called before processing; 403 on failure; `whatsapp.verify_signature` flag; `webhook_app_secret` for HMAC. |
| **3.2** | Rate limiting | **Done** | Per-phone per-minute and optional per-user per-day enforced in `MessageHandler::isRateLimited()`; “Too many messages” reply; rate-limit hits logged. |
| **3.3** | Human handoff | **Done** | Keyword triggers (agent, human, support, complaint, fraud, etc.) and “YES” after offer; consecutive failures (≥2) trigger offer; ticket created with recent conversation context; state `awaiting_agent`; user gets ticket ref. Not implemented: explicit “notify support” (e.g. email) or “expected response time” in message—agents see tickets in dashboard. |
| **3.4** | Audit trail | **Done** | `banking_audit_logs` table; user_id, action_type, resource_id, channel, session_id, metadata; logging for transfer, beneficiary_add, card freeze/unfreeze/block, loan_application, cardless_withdrawal. Not implemented: tamper-evident hashing/signing; logging of AI reasoning/tool calls (optional in doc). |
| **3.5** | Sensitive-action OTP | **Done** | OTP requested via `sensitive-action-request`, sent by WhatsApp; user replies with code; `sensitive-action-confirm` executes and audits. High-risk actions documented in AI system prompt (transfer > USD 500, card block, loan application). |
| **3.6** | Retry + circuit breaker | **Done** | Exponential backoff with jitter; 4xx not retried; circuit opens after 5 failures in 60s, 60s cooldown; “Assistant temporarily unavailable” on final failure. Not implemented: sidecar `Retry-After` on 429 (optional in doc). |
| **3.7** | Safer webhook logging | **Done** | When `log_verbose` is false: no full raw_body/data; no Metadata/Contact (PII); messages log only id, type, from, body_length. Retention/access policy for the `whatsapp` channel is operational (not in code). |
| **3.8** | WhatsApp Flows | **Done** | Flow definitions (transfer, loan, card block, amount+passcode), data endpoint, send-flow internal API, AI prompt updated. Flows must be published in Meta and flow_ids set in .env. See docs/whatsapp-flows-setup.md. |

**Summary:** All items (3.1–3.8) are implemented. Optional or operational items (tamper-evident audit, AI reasoning log, Retry-After, support notification channel, retention policy doc) are either deferred or left to operations.

---

## 8. Further Reading: Additional Considerations

For a broader checklist of what might have been missed (consent, data retention, right to be forgotten, session messaging, fallbacks, localization, tests, user opt-out, attachment handling, etc.), see **[whatsapp-ai-additional-considerations.md](whatsapp-ai-additional-considerations.md)**.
