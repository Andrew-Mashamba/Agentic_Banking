<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsAppCircuitOpened;
use App\Exceptions\SidecarUnavailableException;
use App\Models\User;
use App\Models\WhatsAppRequestLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Agent Service for WhatsApp Banking.
 *
 * Routes WhatsApp conversations to the AI sidecar (Cursor Agent CLI).
 * The AI acts as a senior private banker serving clients via chat.
 */
class AiAgentService
{
    protected WhatsAppService $whatsappService;
    protected GuestMemoryService $memoryService;
    protected UserMemoryService $userMemoryService;
    protected PendingTaskService $pendingTaskService;
    protected WhatsAppComplianceService $complianceService;
    protected WhatsAppMetricsService $metricsService;
    protected string $sidecarUrl;
    protected string $internalApiUrl;

    const WHATSAPP_MAX_CHARS = 3800;

    public function __construct(
        WhatsAppService $whatsappService,
        GuestMemoryService $memoryService,
        UserMemoryService $userMemoryService,
        PendingTaskService $pendingTaskService,
        WhatsAppComplianceService $complianceService,
        WhatsAppMetricsService $metricsService
    ) {
        $this->whatsappService = $whatsappService;
        $this->memoryService = $memoryService;
        $this->userMemoryService = $userMemoryService;
        $this->pendingTaskService = $pendingTaskService;
        $this->complianceService = $complianceService;
        $this->metricsService = $metricsService;
        $this->sidecarUrl = config('whatsapp.sidecar_url', 'http://127.0.0.1:8101/ask');
        $this->internalApiUrl = 'http://127.0.0.1:8080/api/internal';
    }

    /**
     * Process an incoming WhatsApp message via the AI agent.
     */
    public function processMessage(User $user, array $messageData): bool
    {
        $userText = $this->extractUserText($messageData);
        if (empty($userText)) {
            return false;
        }

        $phone = $user->phone_number;

        Log::channel('whatsapp')->info('AI Agent: processing message', [
            'user_id' => $user->id,
            'phone' => $phone,
            'text' => $userText,
        ]);

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPromptWithBudget($user, $userText, $messageData);

        $this->metricsService->incrementAiRequests();
        $this->logRequestVersion($user->id, $phone, $systemPrompt);
        $aiResponse = $this->callSidecar($phone, $systemPrompt, $userPrompt, $user->id);

        if ($aiResponse === null) {
            Log::channel('whatsapp')->warning('AI Agent: sidecar returned null, falling back');
            $this->metricsService->incrementAiFailures();
            return false;
        }

        $aiResponse = $this->sanitizeForWhatsApp($aiResponse);

        if (empty($aiResponse)) {
            Log::channel('whatsapp')->warning('AI Agent: empty response after sanitize');
            return false;
        }

        $this->sendWhatsAppResponse($phone, $aiResponse);
        $this->memoryService->saveConversationTurn($user, $userText, $aiResponse);

        Log::channel('whatsapp')->info('AI Agent: response sent', [
            'user_id' => $user->id,
            'phone' => $phone,
            'response_length' => strlen($aiResponse),
        ]);

        return true;
    }

    /**
     * Extract plain text from any message type.
     */
    protected function extractUserText(array $messageData): string
    {
        $type = $messageData['type'] ?? 'unknown';

        return match ($type) {
            'text' => trim($messageData['text'] ?? ''),
            'interactive' => $messageData['button_title'] ?? $messageData['list_title'] ?? '',
            'button' => trim($messageData['text'] ?? $messageData['button_id'] ?? ''),
            'image', 'document' => trim($messageData['text'] ?? ''),
            default => '',
        };
    }

    /**
     * Build the system prompt for the AI banker.
     *
     * NOTE: The main persona rules are injected by the sidecar from
     * .cursor/rules/whatsapp-banker.mdc. This prompt provides additional
     * operational context.
     */
    protected function buildSystemPrompt(): string
    {
        $system = <<<'SYSTEM'
INTERNAL API ACCESS:
You can query the bank's internal API to fetch real client data. The base URL is:
http://127.0.0.1:8080/api/internal

Always include the X-User-Id header with the client's user ID from the session.

Available endpoints:
- GET /accounts/summary - Get all accounts with balances
- GET /accounts - List accounts
- GET /accounts/{id} - Account details
- GET /transactions?account_id={id}&per_page=10 - Recent transactions
- GET /beneficiaries - Saved beneficiaries
- POST /beneficiaries - Add beneficiary
- GET /transfers - Transfer history
- POST /transfers - Create transfer (body: from_account_id, beneficiary_id, amount, type, reference)
- GET /cards - List cards
- POST /cards/{id}/freeze - Freeze card
- POST /cards/{id}/unfreeze - Unfreeze card
- POST /cards/{id}/block - Block card (lost/stolen)
- GET /loan-products - Available loan products
- GET /loans - Client's loans
- GET /loans/{id}/repayment-schedule - Loan schedule
- GET /fixed-deposits - Fixed deposits
- GET /investment-products - Investment options
- GET /investments - Client's investments
- GET /branches - Bank branches
- GET /atms - ATM locations
- GET /faqs - Frequently asked questions
- GET /pending-tasks - List client's pending (incomplete) tasks
- POST /pending-tasks - Create or update: body { task_type, step?, context? } (e.g. task_type: loan_application, account_opening, kyc)
- POST /pending-tasks/{id}/complete - Mark done: body { status: "completed"|"abandoned" }
- GET /user-memory - Get long-term memory for client
- POST /user-memory - Append fact: body { text: "..." }

USER IDENTITY: The client is identified by phone number (primary). PIN (transaction PIN) is used only to verify identity for sensitive actions, not as a storage key. All memory and pending tasks are stored by user_id (resolved from phone).

LONG-TERM MEMORY AND PENDING TASKS:
You will receive a LONG-TERM MEMORY block (curated facts about this client) and, if any, a PENDING TASKS block. Use long-term memory to personalize. When the client has pending tasks, always ask at the start if they want to continue with any of them before starting something new. When you start a multi-step process (account opening, loan application, KYC, etc.), call POST /pending-tasks with { task_type, step, context } to save progress. When the process completes or the client cancels, call POST /pending-tasks/{id}/complete. This way the client can resume after a disconnect or return days or weeks later.

Example curl via tinker (for transfers):
php artisan tinker --execute="echo json_encode(Http::withHeaders(['X-User-Id' => '3'])->get('http://127.0.0.1:8080/api/internal/accounts/summary')->json());"

For write operations (transfers, beneficiaries):
php artisan tinker --execute="echo json_encode(Http::withHeaders(['X-User-Id' => '3'])->post('http://127.0.0.1:8080/api/internal/transfers', ['from_account_id' => 1, 'beneficiary_id' => 2, 'amount' => 500, 'type' => 'same_bank', 'reference' => 'Payment'])->json());"

WHATSAPP FLOWS (use freely to improve experience):
You may send any WhatsApp Flow at any time when it would improve the client experience. You are NOT limited to using a form only when the client explicitly asks for that action. If a form would make the interaction clearer, faster, or less error-prone (e.g. picking from lists, entering amounts, confirming), send the appropriate flow.

Call POST /send-flow with body: {"flow_type": "<type>", "body_text": "optional message", "button_text": "Open"}. Then tell the client you have sent a form and they can tap the button to fill it in.

All available flow_type values (use any whenever it helps):
- transfer: send money (account, beneficiary, amount, reference)
- loan_application: apply for loan (product, amount, tenor, purpose)
- card_block: permanently block card (select card, confirm)
- amount_passcode: amount and PIN confirmation
- add_beneficiary: save beneficiary (type, name, account number, bank)
- recurring_transfer: standing order (account, beneficiary, amount, frequency)
- cardless_withdrawal: ATM code without card (account, amount)
- loan_repayment: pay a loan (loan, amount, from account)
- fixed_deposit: open FD (account, amount, tenor months)
- investment: invest (product, amount)
- card_freeze: freeze a card (select card)
- card_unfreeze: unfreeze a card (select card)
- book_appointment: book branch visit (branch, date/time)
- support_ticket: create support ticket (subject)

PIN REQUIREMENT: PIN is required for any transfer, withdrawal, or deposit above zero. Do not complete or confirm any such transaction without the client verifying with their transaction PIN. Use the amount_passcode flow (amount + PIN) or the transfer/cardless_withdrawal/fixed_deposit flow so the client enters PIN before the operation is executed, or use POST /sensitive-action-request then POST /sensitive-action-confirm.

SENSITIVE ACTIONS (OTP/code): For high-risk actions (e.g. card block, loan application) you may also use POST /sensitive-action-request then the 6-digit code, then POST /sensitive-action-confirm.

ATTACHMENTS (loan applications, account opening, KYC, etc.):
For processes that require documents (e.g. loan application, account opening, KYC), ask the client to attach the file and to say what the file is (e.g. national ID, proof of address, pay slip). All uploaded files are automatically saved to a predefined storage path. When the client sends an attachment, you will see CLIENT ATTACHMENT with the stored path and their description; acknowledge receipt and proceed with the process. If they did not describe the file, ask them what it is.

IMPORTANT:
- Always use the client's user_id from the CURRENT SESSION block.
- Default currency is TZS. Format currency amounts with commas and 2 decimals (e.g., TZS 15,000.00).
- Mask account numbers in responses (show only last 4 digits).
- Never expose full card numbers.
SYSTEM;
        $disclaimer = $this->complianceService->getAiTermsDisclaimer();
        $system .= "\n\nTERMS FOR AI-DRIVEN ACTIONS:\n" . $disclaimer . "\nFor high-risk actions (transfer, card block, loan application), include this disclaimer in your confirmation so the client knows that proceeding authorizes the bank to execute the action.\n";
        return $system;
    }

    /**
     * Build user prompt within context budget (smart context; see docs/smart-context-and-prompting.md).
     * If first build exceeds budget, rebuilds with stricter caps (fewer turns, transactions, smaller memory).
     */
    protected function buildUserPromptWithBudget(User $user, string $userText, array $messageData = []): string
    {
        $budget = (int) config('whatsapp.context.budget_chars', 600_000);
        $reserve = (int) config('whatsapp.context.reserve_output_chars', 4000);
        $userPromptBudget = max(10000, $budget - $reserve);

        $caps = $this->getDefaultContextCaps();
        $userPrompt = $this->buildUserPrompt($user, $userText, $messageData, $caps);

        if (mb_strlen($userPrompt) <= $userPromptBudget) {
            return $userPrompt;
        }

        Log::channel('whatsapp')->info('AI Agent: user prompt over budget, applying strict caps', [
            'length' => mb_strlen($userPrompt),
            'budget' => $userPromptBudget,
        ]);

        $strictCaps = [
            'max_recent_turns' => max(5, (int) floor($caps['max_recent_turns'] / 2)),
            'max_transactions' => max(3, min(5, $caps['max_transactions'])),
            'max_memory_chars' => max(2000, (int) floor($caps['max_memory_chars'] / 2)),
        ];
        return $this->buildUserPrompt($user, $userText, $messageData, $strictCaps);
    }

    /**
     * Default context caps from config (whatsapp.context).
     */
    protected function getDefaultContextCaps(): array
    {
        $ctx = config('whatsapp.context', []);

        return [
            'max_recent_turns' => (int) ($ctx['max_recent_turns'] ?? 20),
            'max_transactions' => (int) ($ctx['max_transactions'] ?? 10),
            'max_memory_chars' => (int) ($ctx['max_memory_chars'] ?? 8000),
        ];
    }

    /**
     * Build the user prompt with client context.
     * Optional $contextCaps: max_recent_turns, max_transactions, max_memory_chars (overrides config when set).
     */
    protected function buildUserPrompt(User $user, string $userText, array $messageData = [], ?array $contextCaps = null): string
    {
        $caps = $contextCaps ?? $this->getDefaultContextCaps();
        $parts = [];

        // Session context (high priority; always include)
        $parts[] = $this->buildSessionContext($user);

        // Preferred language (so AI replies in that language when set)
        $preferredLang = $this->complianceService->getPreferredLanguage($user);
        if ($preferredLang !== '') {
            $parts[] = "PREFERRED LANGUAGE: The client prefers to receive replies in: {$preferredLang}. Respond in that language unless the client writes in another language.";
        }

        // Long-term memory only if not disabled by user preference (capped by max_memory_chars)
        if (! $this->complianceService->disableLongTermMemory($user)) {
            $longTerm = $this->userMemoryService->getForPrompt($user, $caps['max_memory_chars']);
            if ($longTerm !== '') {
                $parts[] = $longTerm;
            }
        }

        // Pending tasks (offer to resume if any)
        $pendingBlock = $this->pendingTaskService->getForPrompt($user);
        if ($pendingBlock !== '') {
            $parts[] = $pendingBlock;
        }

        // Client memory (recent conversation history; capped by max_recent_turns)
        $parts[] = $this->memoryService->buildMemoryContext($user, $caps['max_recent_turns']);

        // Pre-fetched account summary (eliminates API call for balance inquiries)
        $parts[] = $this->buildAccountsContext($user);

        // Recent transactions (capped by max_transactions)
        $parts[] = $this->buildRecentTransactionsContext($user, $caps['max_transactions']);

        // Active products (loans, cards)
        $parts[] = $this->buildActiveProductsContext($user);

        // Attachment (saved to predefined storage path)
        if (! empty($messageData['attachment_path'])) {
            $type = $messageData['attachment_type'] ?? 'file';
            $caption = $messageData['attachment_caption'] ?? null;
            $path = $messageData['attachment_path'];
            $desc = $caption
                ? "Client says this file is: {$caption}"
                : 'Client did not describe the file. Ask them what this document is (e.g. national ID, proof of address, pay slip).';
            $parts[] = "CLIENT ATTACHMENT:\nThe client has uploaded a file. Type: {$type}. Stored at: {$path}. {$desc}";
        }

        // The client's message (always last — "lost in the middle" mitigation)
        $parts[] = "CLIENT MESSAGE:\n{$userText}";

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Build session context block.
     */
    protected function buildSessionContext(User $user): string
    {
        $primaryAccount = $user->accounts()->where('is_primary', true)->first();
        $primaryAccountInfo = $primaryAccount
            ? "Primary Account: {$primaryAccount->type} ending in " . substr($primaryAccount->account_number, -4) . " ({$primaryAccount->currency})"
            : "Primary Account: Not set";

        return <<<SESSION
=== CURRENT SESSION ===
Client Name: {$user->name}
User ID: {$user->id}
Phone: {$user->phone_number}
Email: {$user->email}
Status: {$user->status}
{$primaryAccountInfo}
Time: {$this->currentTime()}
=== END SESSION ===
SESSION;
    }

    /**
     * Build accounts context with balances.
     */
    protected function buildAccountsContext(User $user): string
    {
        $accounts = $user->accounts()->where('status', 'active')->get();

        if ($accounts->isEmpty()) {
            return "=== ACCOUNTS ===\nNo active accounts found.\n=== END ACCOUNTS ===";
        }

        $lines = ["=== ACCOUNTS ==="];
        $totalTzs = 0;

        foreach ($accounts as $account) {
            $masked = '****' . substr($account->account_number, -4);
            $balance = number_format($account->balance, 2);
            $primary = $account->is_primary ? ' [PRIMARY]' : '';
            $lines[] = "- {$account->type} ({$masked}): {$account->currency} {$balance}{$primary}";

            if ($account->currency === 'TZS') {
                $totalTzs += $account->balance;
            }
        }

        $lines[] = "Total Balance (TZS): " . number_format($totalTzs, 2);
        $lines[] = "=== END ACCOUNTS ===";

        return implode("\n", $lines);
    }

    /**
     * Build recent transactions context (capped by max_transactions from config or context caps).
     */
    protected function buildRecentTransactionsContext(User $user, ?int $maxTransactions = null): string
    {
        $accountIds = $user->accounts()->pluck('id');

        if ($accountIds->isEmpty()) {
            return '';
        }

        $limit = $maxTransactions ?? config('whatsapp.context.max_transactions', 10);
        $transactions = \App\Models\Transaction::whereIn('account_id', $accountIds)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($transactions->isEmpty()) {
            return '';
        }

        $lines = ["=== RECENT TRANSACTIONS (last {$limit}) ==="];

        foreach ($transactions as $tx) {
            $date = $tx->created_at->format('M j');
            $sign = $tx->type === 'credit' ? '+' : '-';
            $amount = number_format($tx->amount, 2);
            $desc = mb_substr($tx->description ?? $tx->reference ?? 'Transaction', 0, 30);
            $lines[] = "- {$date}: {$sign}{$tx->currency} {$amount} - {$desc}";
        }

        $lines[] = "=== END TRANSACTIONS ===";

        return implode("\n", $lines);
    }

    /**
     * Build active products context (cards, loans).
     */
    protected function buildActiveProductsContext(User $user): string
    {
        $parts = [];

        // Cards
        $cards = \App\Models\Card::where('user_id', $user->id)
            ->whereIn('status', ['active', 'frozen'])
            ->get();

        if ($cards->isNotEmpty()) {
            $cardLines = ["=== CARDS ==="];
            foreach ($cards as $card) {
                $masked = '****' . substr($card->card_number, -4);
                $status = strtoupper($card->status);
                $cardLines[] = "- {$card->card_type} ({$masked}): {$status}";
            }
            $cardLines[] = "=== END CARDS ===";
            $parts[] = implode("\n", $cardLines);
        }

        // Loans
        $loans = \App\Models\Loan::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        if ($loans->isNotEmpty()) {
            $loanLines = ["=== ACTIVE LOANS ==="];
            foreach ($loans as $loan) {
                $balance = number_format($loan->outstanding_balance, 2);
                $loanLines[] = "- {$loan->loanProduct->name}: TZS {$balance} outstanding";
            }
            $loanLines[] = "=== END LOANS ===";
            $parts[] = implode("\n", $loanLines);
        }

        return implode("\n\n", $parts);
    }

    protected function logRequestVersion(int $userId, string $phone, string $systemPrompt): void
    {
        $version = config('whatsapp.prompt_version', '1.0');
        $hash = hash('sha256', mb_substr($systemPrompt, 0, 5000));
        try {
            WhatsAppRequestLog::create([
                'user_id' => $userId,
                'phone_number' => $phone,
                'prompt_version' => $version,
                'prompt_hash' => $hash,
            ]);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->debug('Request log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Call the AI sidecar. Throws SidecarUnavailableException on 5xx/429/timeout/circuit open (retryable).
     */
    protected function callSidecar(string $phone, string $systemPrompt, string $userPrompt, ?int $userId = null): ?string
    {
        if (Cache::get('wa:circuit:open')) {
            Log::channel('whatsapp')->warning('AI Agent: circuit open, skipping sidecar call', ['phone' => $phone]);
            throw new SidecarUnavailableException('Circuit breaker open');
        }

        $promptVersion = config('whatsapp.prompt_version', '1.0');
        try {
            $safeSystem = mb_convert_encoding($systemPrompt, 'UTF-8', 'UTF-8');
            $safePrompt = mb_convert_encoding($userPrompt, 'UTF-8', 'UTF-8');

            $body = [
                'phone_number' => $phone,
                'system_prompt' => $safeSystem,
                'prompt' => $safePrompt,
                'max_chars' => self::WHATSAPP_MAX_CHARS,
                'prompt_version' => $promptVersion,
            ];
            $response = Http::timeout(180)
                ->post($this->sidecarUrl, $body);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['success'] ?? false) && isset($data['data']['answer'])) {
                    Cache::forget('wa:circuit:failures');
                    return trim($data['data']['answer']);
                }
            }

            $status = $response->status();
            if ($status === 429) {
                Log::channel('whatsapp')->info('AI Agent: sidecar busy', ['phone' => $phone]);
                $this->recordSidecarFailure();
                throw new SidecarUnavailableException('Sidecar rate limited');
            }

            if ($status >= 500 || $status === 504) {
                Log::channel('whatsapp')->error('AI Agent: sidecar error', [
                    'phone' => $phone,
                    'status' => $status,
                    'body' => substr($response->body(), 0, 500),
                ]);
                $this->recordSidecarFailure();
                throw new SidecarUnavailableException('Sidecar server error');
            }

            // 4xx client errors: do not retry
            Log::channel('whatsapp')->warning('AI Agent: sidecar client error', [
                'phone' => $phone,
                'status' => $status,
                'body' => substr($response->body(), 0, 500),
            ]);
            return null;
        } catch (SidecarUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('AI Agent: sidecar request failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            $this->recordSidecarFailure();
            throw new SidecarUnavailableException($e->getMessage(), 0, $e);
        }
    }

    protected function recordSidecarFailure(): void
    {
        $key = 'wa:circuit:failures';
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, 60);
        if ($count >= 5) {
            Cache::put('wa:circuit:open', true, 60);
            Log::channel('whatsapp')->warning('AI Agent: circuit breaker opened after 5 failures');
            event(new WhatsAppCircuitOpened('5 consecutive sidecar failures'));
        }
    }

    /**
     * Send AI response to WhatsApp.
     */
    protected function sendWhatsAppResponse(string $phone, string $text): void
    {
        if (strlen($text) <= self::WHATSAPP_MAX_CHARS) {
            $this->whatsappService->sendTextMessage($phone, $text);
            return;
        }

        $chunks = $this->splitMessage($text, self::WHATSAPP_MAX_CHARS);

        foreach ($chunks as $i => $chunk) {
            if (count($chunks) > 1) {
                $part = ($i + 1) . '/' . count($chunks);
                $chunk = trim($chunk) . "\n({$part})";
            }
            $this->whatsappService->sendTextMessage($phone, $chunk);
        }
    }

    /**
     * Split a long message into chunks.
     */
    protected function splitMessage(string $text, int $maxChars): array
    {
        $chunks = [];
        $remaining = $text;

        while (strlen($remaining) > $maxChars) {
            $chunk = substr($remaining, 0, $maxChars);
            $lastParagraph = strrpos($chunk, "\n\n");

            if ($lastParagraph !== false && $lastParagraph > $maxChars * 0.3) {
                $splitAt = $lastParagraph;
            } else {
                $lastNewline = strrpos($chunk, "\n");
                if ($lastNewline !== false && $lastNewline > $maxChars * 0.3) {
                    $splitAt = $lastNewline;
                } else {
                    $lastSpace = strrpos($chunk, ' ');
                    $splitAt = $lastSpace !== false ? $lastSpace : $maxChars;
                }
            }

            $chunks[] = trim(substr($remaining, 0, $splitAt));
            $remaining = trim(substr($remaining, $splitAt));
        }

        if (!empty($remaining)) {
            $chunks[] = trim($remaining);
        }

        return $chunks;
    }

    /**
     * Clean up AI response for WhatsApp.
     */
    protected function sanitizeForWhatsApp(string $text): string
    {
        // Remove preambles
        $text = preg_replace('/^(Here\'s|Use this|Below is|This is).*?:\s*\n/i', '', $text);

        // Remove --- delimiters
        $text = preg_replace('/^---\s*$/m', '', $text);

        // Strip markdown bold
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/__(.+?)__/', '$1', $text);

        // Strip markdown italic
        $text = preg_replace('/(?<!\w)\*([^*\n]+?)\*(?!\w)/', '$1', $text);

        // Strip markdown headers
        $text = preg_replace('/^#{1,3}\s+/m', '', $text);

        // Strip backtick code
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        // Strip code blocks
        $text = preg_replace('/```[\s\S]*?```/', '', $text);

        // Collapse multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Get current time formatted for prompt.
     */
    protected function currentTime(): string
    {
        return now()->format('l, F j, Y g:i A');
    }
}
