<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure WhatsApp Business Cloud API settings for sending messages
    | and handling webhooks.
    |
    */

    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0'),

    'access_token' => env('WHATSAPP_API_TOKEN'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'webhook_secret' => env('WHATSAPP_VERIFY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Verification (Meta App Secret for X-Hub-Signature-256)
    |--------------------------------------------------------------------------
    */
    'verify_signature' => env('WHATSAPP_VERIFY_SIGNATURE', true),
    'webhook_app_secret' => env('WHATSAPP_APP_SECRET', env('WHATSAPP_VERIFY_TOKEN')),

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    |
    | Pre-approved WhatsApp message template names
    |
    */

    'templates' => [
        'welcome' => env('WHATSAPP_TEMPLATE_WELCOME', 'welcome_message'),
        'order_received' => env('WHATSAPP_TEMPLATE_ORDER_RECEIVED', 'order_received'),
        'order_ready' => env('WHATSAPP_TEMPLATE_ORDER_READY', 'order_ready'),
        'bill_summary' => env('WHATSAPP_TEMPLATE_BILL_SUMMARY', 'bill_summary'),
        'thank_you' => env('WHATSAPP_TEMPLATE_THANK_YOU', 'thank_you'),
        'reservation_confirmed' => env('WHATSAPP_TEMPLATE_RESERVATION_CONFIRMED', 'reservation_confirmed'),
        'payment_receipt' => env('WHATSAPP_TEMPLATE_PAYMENT_RECEIPT', 'payment_receipt'),
        'waiter_requested' => env('WHATSAPP_TEMPLATE_WAITER_REQUESTED', 'waiter_requested'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Guest session timeout and state management
    |
    */

    'session_timeout' => env('WHATSAPP_SESSION_TIMEOUT', 3600), // 1 hour in seconds

    'max_order_items' => env('WHATSAPP_MAX_ORDER_ITEMS', 20),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent abuse with rate limiting
    |
    */

    'rate_limit' => [
        'enabled' => env('WHATSAPP_RATE_LIMIT_ENABLED', true),
        'max_messages_per_minute' => env('WHATSAPP_RATE_LIMIT_PER_MINUTE', 10),
        'max_messages_per_day_per_user' => env('WHATSAPP_RATE_LIMIT_PER_DAY', 200), // 0 = no daily cap
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable/disable logging of WhatsApp messages
    |
    */

    'log_messages' => env('WHATSAPP_LOG_MESSAGES', true),

    /*
    |--------------------------------------------------------------------------
    | Verbose Webhook Logging (set false in production to avoid PII in logs)
    |--------------------------------------------------------------------------
    */
    'log_verbose' => env('WHATSAPP_LOG_VERBOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Restaurant Info
    |--------------------------------------------------------------------------
    |
    | Restaurant details used for QR codes and messages
    |
    */

    'restaurant' => [
        'phone' => env('WHATSAPP_RESTAURANT_PHONE'),
        'name' => env('WHATSAPP_RESTAURANT_NAME', 'SeaCliff Restaurant'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Configuration
    |--------------------------------------------------------------------------
    |
    | Storage and generation settings for QR codes
    |
    */

    'qr_code' => [
        'storage_path' => env('WHATSAPP_QR_STORAGE_PATH', 'qr-codes/tables'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Flows (structured in-chat forms: lists, amounts, PIN, etc.)
    |--------------------------------------------------------------------------
    | data_channel_uri: HTTPS URL Meta will POST to for dynamic data (must be public).
    | flow_ids: Published flow IDs from Meta (create via Graph API or Business Suite).
    */
    'flows' => [
        'data_channel_uri' => env('WHATSAPP_FLOWS_DATA_URI', env('APP_URL', 'http://localhost') . '/api/webhooks/whatsapp-flows/data'),
        'flow_ids' => [
            'transfer' => env('WHATSAPP_FLOW_ID_TRANSFER'),
            'loan_application' => env('WHATSAPP_FLOW_ID_LOAN'),
            'card_block' => env('WHATSAPP_FLOW_ID_CARD_BLOCK'),
            'amount_passcode' => env('WHATSAPP_FLOW_ID_AMOUNT_PASSCODE'),
            'add_beneficiary' => env('WHATSAPP_FLOW_ID_ADD_BENEFICIARY'),
            'recurring_transfer' => env('WHATSAPP_FLOW_ID_RECURRING_TRANSFER'),
            'cardless_withdrawal' => env('WHATSAPP_FLOW_ID_CARDLESS_WITHDRAWAL'),
            'loan_repayment' => env('WHATSAPP_FLOW_ID_LOAN_REPAYMENT'),
            'fixed_deposit' => env('WHATSAPP_FLOW_ID_FIXED_DEPOSIT'),
            'investment' => env('WHATSAPP_FLOW_ID_INVESTMENT'),
            'card_freeze' => env('WHATSAPP_FLOW_ID_CARD_FREEZE'),
            'card_unfreeze' => env('WHATSAPP_FLOW_ID_CARD_UNFREEZE'),
            'book_appointment' => env('WHATSAPP_FLOW_ID_BOOK_APPOINTMENT'),
            'support_ticket' => env('WHATSAPP_FLOW_ID_SUPPORT_TICKET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Attachments (documents, images uploaded by clients)
    |--------------------------------------------------------------------------
    | storage_path: Relative to storage_path() (e.g. app/whatsapp_attachments)
    | or set WHATSAPP_ATTACHMENTS_PATH to an absolute path. All files received
    | for loan applications, account opening, KYC, etc. are saved here.
    */
    'attachments' => [
        'storage_path' => env('WHATSAPP_ATTACHMENTS_PATH', 'whatsapp_attachments'),
        'max_bytes' => env('WHATSAPP_ATTACHMENT_MAX_BYTES', 10 * 1024 * 1024), // 10 MB
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'],
        'scan_enabled' => env('WHATSAPP_ATTACHMENT_SCAN_ENABLED', false),
        'scanner' => env('WHATSAPP_ATTACHMENT_SCANNER', 'null'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance: consent, retention, opt-out (override via settings table)
    |--------------------------------------------------------------------------
    */
    'compliance' => [
        'consent_required' => env('WHATSAPP_CONSENT_REQUIRED', true),
        'disclosure_message' => env('WHATSAPP_DISCLOSURE_MESSAGE', 'You are chatting with our AI banking assistant. We use your messages to process requests and improve service. By continuing you agree to our WhatsApp banking terms and privacy policy. Reply YES to continue.'),
        'privacy_policy_url' => env('WHATSAPP_PRIVACY_POLICY_URL', ''),
        'retention_days_conversations' => (int) env('WHATSAPP_RETENTION_DAYS_CONVERSATIONS', 365),
        'retention_days_pending_tasks' => (int) env('WHATSAPP_PENDING_TASK_TTL_DAYS', 90),
        'retention_days_attachments' => (int) env('WHATSAPP_RETENTION_DAYS_ATTACHMENTS', 90),
        'retention_days_memory' => (int) env('WHATSAPP_RETENTION_DAYS_MEMORY', 365),
        'audit_log_retention_years' => (int) env('WHATSAPP_AUDIT_RETENTION_YEARS', 7),
        'pending_task_max_per_user' => (int) env('WHATSAPP_PENDING_TASK_MAX_PER_USER', 5),
        'supported_languages' => ['en', 'sw'],
        'open_banking_consent_enabled' => env('WHATSAPP_OPEN_BANKING_CONSENT', false),
        'open_banking_consent_purpose' => env('WHATSAPP_OB_CONSENT_PURPOSE', 'To provide you with WhatsApp banking services, balance checks, transfers, and support.'),
        'open_banking_consent_benefit' => env('WHATSAPP_OB_CONSENT_BENEFIT', 'Faster service and 24/7 access to your accounts via chat.'),
        'open_banking_consent_data' => env('WHATSAPP_OB_CONSENT_DATA', 'Conversation history, account identifiers, and transaction details you share.'),
        'open_banking_consent_duration_months' => (int) env('WHATSAPP_OB_CONSENT_DURATION_MONTHS', 12),
        'ai_terms_disclaimer' => env('WHATSAPP_AI_TERMS_DISCLAIMER', 'Proceeding authorizes the bank to execute this action. Information provided by the assistant is as is.'),
    ],

    'prompt_version' => env('WHATSAPP_PROMPT_VERSION', '1.0'),

    'log_retention_days' => (int) env('WHATSAPP_LOG_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Smart Context (docs/smart-context-and-prompting.md)
    |--------------------------------------------------------------------------
    | Caps and budget for user-prompt context to avoid bloat and "lost in the
    | middle". Sidecar allows ~800k chars (200k tokens); we keep user context
    | under budget_chars so system prompt + user context + reply fit.
    */
    'context' => [
        'max_recent_turns' => (int) env('WHATSAPP_CONTEXT_MAX_RECENT_TURNS', 20),
        'max_transactions' => (int) env('WHATSAPP_CONTEXT_MAX_TRANSACTIONS', 10),
        'max_memory_chars' => (int) env('WHATSAPP_CONTEXT_MAX_MEMORY_CHARS', 8000),
        'max_conversation_turn_chars' => (int) env('WHATSAPP_CONTEXT_MAX_TURN_CHARS', 300),
        'budget_chars' => (int) env('WHATSAPP_CONTEXT_BUDGET_CHARS', 600_000),
        'reserve_output_chars' => (int) env('WHATSAPP_CONTEXT_RESERVE_OUTPUT_CHARS', 4000),
    ],

    'sidecar_url' => env('WHATSAPP_SIDECAR_URL', 'http://127.0.0.1:8101/ask'),

];
