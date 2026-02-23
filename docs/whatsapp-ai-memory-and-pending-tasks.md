# WhatsApp AI: Long-Term Memory and Pending Tasks

## Research: OpenClaw and AI Agent Memory

### How OpenClaw Stores Long-Term Memory

OpenClaw uses a **three-layer memory system** (see [Learn OpenClaw – Memory](https://learnopenclaw.com/core-concepts/memory)):

1. **Short-term (conversation context)**  
   The current chat context in the LLM’s context window. No persistence.

2. **Medium-term (daily logs)**  
   Timestamped markdown files per day, updated by a `session-memory` hook at the end of each conversation. Captures what was discussed, decisions, and actions. Append-only.

3. **Long-term (MEMORY.md)**  
   A single curated file loaded at every session (via `boot-md`). Contains persistent facts, preferences, key decisions, active projects, and people. Human- or agent-maintained; kept concise (e.g. under 200 lines). Hybrid search (keyword + semantic) can be used for recall.

Memory is **plain markdown on disk** (no vector DB required). Persistence happens when written to disk. The agent always starts with MEMORY.md in context so it “remembers” the user.

### Patterns We Apply for WhatsApp Banking

- **User identity**: Identify the user by **phone number** (primary) and **PIN** (transaction PIN for verification). All memory and tasks are keyed by **user_id** (resolved from phone). PIN is not used as a storage key; it is used to verify identity for sensitive actions.
- **Long-term memory**: One durable store per user (e.g. a “MEMORY” row or file) that holds curated facts, preferences, and important interaction summaries. Injected into every AI prompt so the agent remembers the client across days or weeks.
- **Interaction history**: Keep a log of user interactions (we already have `guest_conversations`). Optionally summarize recent sessions into the long-term memory periodically.
- **Pending tasks**: Persist multi-step process state (e.g. account opening, loan application, KYC) in a **pending_tasks** table. When connectivity drops or the user leaves and returns later (next day/week), the AI loads pending tasks and asks: “You have an incomplete [X]. Do you want to continue?”
- **Resume across sessions**: User can start a task today and finish tomorrow or next week. The AI checks for pending tasks at the start of each turn and offers to resume.

---

## Design in This Codebase

### 1. User Identification

- **Primary identifier**: `phone_number` (used to resolve `user_id` from `users`).
- **Security identifier**: **PIN** (transaction PIN). Used to verify the user for sensitive operations (transfers, withdrawals, etc.), not as a DB key. Stored as `transaction_pin_hash` on `users`.
- Memory and pending tasks are stored by **user_id**; the session is linked via phone so we always resolve user from phone first.

### 2. Long-Term Memory (per user)

- **Table**: `whatsapp_user_memory`
  - `user_id` (unique)
  - `memory_text` (longtext): Curated facts, preferences, and summaries (like MEMORY.md).
  - `updated_at`
- **Behaviour**:
  - Loaded into every AI prompt so the agent always has “who this client is” and past context.
  - Can be updated by the AI (via internal API) or by backend when important events occur (e.g. “Client completed KYC on …”, “Preferred language: Swahili”).
  - Optionally: append short session summaries (medium-term) and periodically merge into `memory_text` to avoid unbounded growth.

### 3. Pending Tasks (resumable workflows)

- **Table**: `whatsapp_pending_tasks`
  - `id`, `user_id`, `phone_number` (denormalized for quick lookup)
  - `task_type` (e.g. `account_opening`, `loan_application`, `kyc`, `transfer`)
  - `step` or `status` (e.g. “step_2”, “awaiting_documents”)
  - `context` (JSON): Collected data so far (amounts, product id, documents path, etc.)
  - `status`: `pending` | `completed` | `abandoned`
  - `created_at`, `updated_at`
- **Behaviour**:
  - When the AI (or a flow) starts a multi-step process, it creates a pending task (or updates an existing one) via internal API.
  - On each new message, the backend loads pending tasks for the user and injects them into the prompt: “You have 1 pending task: Loan application (step 2 of 4). Do you want to continue?”
  - When the process completes or is cancelled, the task is marked completed or abandoned.
  - Tasks can span days or weeks; we do not auto-expire them by default (optional TTL can be added later).

### 4. AI Behaviour

- **Start of conversation**: Include long-term memory + list of pending tasks (if any). If there are pending tasks, the AI should briefly list them and ask if the user wants to continue with any.
- **During a multi-step process**: The AI (or flow handler) calls internal API to create/update the pending task so progress is saved. If the user goes silent or connectivity drops, the next time they message we still have the task and can resume.
- **End of process**: Call internal API to mark the pending task completed (or abandoned).
- **User says “continue” or “finish my application”**: AI uses the pending task context to resume from the last step.

### 5. Internal API (for the AI / backend)

- `GET /api/internal/pending-tasks` — List pending tasks for the current user (X-User-Id).
- `POST /api/internal/pending-tasks` — Create or update: `{ task_type, step, context }`.
- `POST /api/internal/pending-tasks/{id}/complete` — Mark completed or abandoned.
- `GET /api/internal/user-memory` — Get current long-term memory for prompt.
- `POST /api/internal/user-memory` — Append or update memory (e.g. “Client prefers TZS”, “Started loan application on …”). Optional: merge/summarize to avoid huge blobs.

---

## File and Code Touchpoints

- **Migrations**: `whatsapp_user_memory`, `whatsapp_pending_tasks`.
- **Models**: `WhatsAppUserMemory`, `WhatsAppPendingTask`.
- **Services**: `UserMemoryService` (get/append memory), `PendingTaskService` (CRUD and “get for prompt”).
- **Internal routes**: `GET/POST /api/internal/user-memory`, `GET/POST /api/internal/pending-tasks`, `POST /api/internal/pending-tasks/{id}/complete`.
- **AiAgentService**: Injects long-term memory and pending tasks into every user prompt; system prompt instructs the AI to register/update/complete pending tasks and to offer to resume when pending tasks exist.
- **FlowDataHandler**: When a flow returns SUCCESS, calls `PendingTaskService::completeByType($user, $flowType)` so completing a flow clears the corresponding pending task automatically.
- **Banker rule**: Same instructions; emphasize phone number and PIN as identifiers and “always check pending tasks and offer to continue”.

This gives the WhatsApp AI durable memory and resumable workflows so it can remember the user and where any process left off, even after long pauses or connectivity issues.
