# Smart Context in AI Agents and Prompting LLMs

This document summarizes research on context-window limits (Cursor Agent CLI / Claude), smart context management for LLM agents, and practical guidance for the WhatsApp banker sidecar and Laravel prompt builder.

---

## 1. Cursor Agent CLI and Context Limits

### Model context window

- **Claude (used by Cursor)** supports a **200,000 token** context window (Claude 3.5 Sonnet / 3.7; Max Mode uses the full 200k).
- **Rule of thumb:** 1 token ≈ 4 characters for English (Anthropic, OpenAI, and others).
- **Therefore:** 200,000 tokens ≈ **800,000 characters** of prompt + response.

### Our alignment

- The **AI sidecar** (`scripts/ai-assistant.py`) limits the *combined* prompt (banker rules + system_prompt + user prompt) to match this capacity:
  - **Default:** `MAX_PROMPT_LEN = 800_000` (overridable via `AI_MAX_PROMPT_LEN`).
  - **Request body:** `MAX_BODY_BYTES = 2_000_000` (2MB) so the JSON payload can carry up to ~800k characters.

This keeps the sidecar within what the Cursor Agent CLI (and underlying Claude) can handle in one call.

### Practical caveats

- Some sources suggest **~120k tokens** as the practical ceiling before quality degrades; the rest of the 200k can still be used but may be less reliable.
- Very long instruction lists can hurt relevance; splitting into iterative chunks is sometimes preferable (Cursor forum, “Maximum prompt size”).

---

## 2. Smart Context: Research Overview

### Why it matters

- **Context rot / bloat:** As conversation and tool outputs grow, cost and latency rise; reasoning can degrade (“lost in the middle”).
- **Scaling cost:** Transformer self-attention scales roughly with sequence length, so long context is expensive.
- **Relevance:** Old or redundant content dilutes important information.

### Strategies from research

| Strategy | Idea | Example / source |
|----------|------|-------------------|
| **Priority-based selection** | Put the most relevant blocks first (or only include them). | Reserve budget for: system instructions → query → retrieved content → output. |
| **Summarization** | Compress history into shorter “gist” or summaries. | ReadAgent: gist memories + lookup into original text; ~20× effective context. |
| **Compression guidelines** | Use NL instructions to decide what to keep/drop. | ACON: “compression guideline optimization” in natural language; 26–54% token reduction. |
| **Sliding window** | Keep only the last N turns or tokens. | Simple and cheap; can match or beat LLM summarization in some settings. |
| **Observation masking** | Drop older observations instead of summarizing. | Can match summarization quality at lower cost in some agent benchmarks. |
| **Semantic compression** | Remove redundancy, keep information-dense segments. | LongLLMLingua-style compression; large token/cost savings. |
| **Structured context** | Separate “working memory” vs “long-term knowledge.” | Focus Agent: consolidate into persistent “Knowledge” blocks; prune raw history. |

### Takeaways for our stack

- **Pre-fetch and structure:** We already inject session, accounts, recent transactions, pending tasks, and long-term memory in **ordered blocks** (e.g. session first, then memory, then accounts). This is a form of priority-based context.
- **Keep blocks concise:** Shorter, scoped blocks (e.g. “last 5 transactions”) reduce bloat and help the model attend to the right part.
- **Optional truncation:** If total prompt size ever approaches the limit, we can:
  - Trim or cap “recent conversation” to last K turns.
  - Cap “recent transactions” (e.g. 5–10).
  - Summarize long-term memory into a fixed-size paragraph when it grows large.
- **No need to over-summarize:** Research suggests that simple truncation/masking can be as effective as heavy LLM summarization in some cases; we can start with caps and only add summarization if needed.

---

## 3. Prompt Engineering for Long Context

### Context budget

Treat the context window as a **budget** and allocate it explicitly:

- **System / rules:** Banker rules + operational instructions (fixed).
- **Session + identity:** User id, phone, primary account (small, high priority).
- **Memory + pending tasks:** Long-term facts and incomplete tasks (high value).
- **Recent conversation:** Last N turns (bounded).
- **Structured data:** Accounts, recent transactions, active products (bounded).
- **Current message:** User’s latest message (always included).
- **Output:** Reserve space for the model’s reply (e.g. 500–1000 tokens).

### Order and “lost in the middle”

- Models often perform better on content at the **beginning** and **end** of the context; middle content can be under-attended.
- Put **critical instructions and identity** near the start, and the **current query** at the end; put large, reference-style data (e.g. transaction lists) in the middle if needed.

### Length and clarity

- **Concise system prompts** reduce noise and latency.
- **Explicit length hints** (e.g. “Keep your response under 3800 characters”) help keep replies within WhatsApp limits and reduce wasted tokens.

---

## 4. Implementation (This Codebase) — Implemented

### Sidecar (`scripts/ai-assistant.py`)

- **`MAX_PROMPT_LEN`** default 800,000 characters (≈200k tokens); set via `AI_MAX_PROMPT_LEN`.
- **`MAX_BODY_BYTES`** default 2MB; set via `AI_MAX_BODY_BYTES`.
- Full prompt = banker rules + system_prompt + “CLIENT MESSAGE:\n” + prompt; all must fit in `MAX_PROMPT_LEN`.

### Laravel config (`config/whatsapp.php` → `whatsapp.context`)

| Key | Default | Env | Purpose |
|-----|---------|-----|---------|
| `max_recent_turns` | 20 | `WHATSAPP_CONTEXT_MAX_RECENT_TURNS` | Max conversation turns in recent history (sliding window). |
| `max_transactions` | 10 | `WHATSAPP_CONTEXT_MAX_TRANSACTIONS` | Max recent transactions lines in context. |
| `max_memory_chars` | 8000 | `WHATSAPP_CONTEXT_MAX_MEMORY_CHARS` | Max characters of long-term memory in prompt (truncate if over). |
| `max_conversation_turn_chars` | 300 | `WHATSAPP_CONTEXT_MAX_TURN_CHARS` | Max chars per turn when formatting conversation history. |
| `budget_chars` | 600000 | `WHATSAPP_CONTEXT_BUDGET_CHARS` | Target max for user context; if exceeded, strict caps applied. |
| `reserve_output_chars` | 4000 | `WHATSAPP_CONTEXT_RESERVE_OUTPUT_CHARS` | Reserved for model reply when computing budget. |

Also: `whatsapp.sidecar_url` (default `http://127.0.0.1:8101/ask`) for the AI sidecar endpoint.

### Laravel (`AiAgentService`, `GuestMemoryService`, `UserMemoryService`)

- **System prompt:** Internal API, identity, PIN/flows, terms. Banker rules are injected by the sidecar.
- **User prompt (context):** Built from ordered blocks (priority-based): session → language → long-term memory → pending tasks → recent conversation → accounts → recent transactions → active products → attachment (if any) → **client message last** (mitigates “lost in the middle”).
- **Caps applied:**
  - **Recent conversation:** `GuestMemoryService::buildMemoryContext($user, $maxTurns)` uses `max_recent_turns`; each turn truncated to `max_conversation_turn_chars`.
  - **Long-term memory:** `UserMemoryService::getForPrompt($user, $maxChars)` truncates to `max_memory_chars` with “… (truncated)” when over.
  - **Recent transactions:** `buildRecentTransactionsContext($user, $maxTransactions)` uses `max_transactions`.
- **Budget enforcement:** `buildUserPromptWithBudget()` builds once with config caps; if length > `budget_chars - reserve_output_chars`, it rebuilds with **strict caps** (half turns, max 5 transactions, half memory) and logs.

### References (high level)

- Claude 200k context (Anthropic); Cursor Max Mode / Agent CLI (Cursor blog, forum).
- Token ≈ 4 characters (OpenAI, Anthropic, common practice).
- LongLLMLingua (prompt compression); ACON (agent context compression); ReadAgent (gist memory); Focus Agent (knowledge consolidation); observation masking vs summarization (recent agent benchmarks).
- “Context windows for LLMs” and “context management” (AverageDevs, PraisonAI, IBM).

---

## 5. Summary

- **Sidecar limit** is aligned with Cursor Agent CLI / Claude: **800k characters** (~200k tokens), configurable via `AI_MAX_PROMPT_LEN` / `AI_MAX_BODY_BYTES`.
- **Smart context (implemented):** Priority order of blocks, bounded recent turns and transactions, capped long-term memory, and budget-based strict caps when user context exceeds `budget_chars`.
- **Prompting:** Critical info at start (session, identity), client message at end; reserved output space; configurable caps and budget in `config/whatsapp.php` under `context`.
