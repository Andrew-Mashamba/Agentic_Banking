# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Development Commands

```bash
# Setup
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build

# Development (runs server, queue, logs, vite concurrently)
composer dev

# Or run individually
php artisan serve              # HTTP server
php artisan queue:listen       # Job queue
php artisan reverb:start       # WebSocket server
npm run dev                    # Vite dev server

# Testing
composer test                  # Run all tests
php artisan test --filter=ApiV1EndpointsTest  # Single test class
php artisan test --filter=test_accounts_index # Single test method

# Database
php artisan migrate:fresh --seed   # Reset and seed
php artisan tinker                 # REPL

# Logs
php artisan pail                   # Real-time log viewer
```

## Architecture Overview

### Two-System Design

This is a Laravel 11 digital banking platform with **two distinct interfaces**:

1. **REST API** (`/api/v1/*`) - Mobile/web client access via Sanctum Bearer tokens
2. **WhatsApp AI Chatbot** - Conversational banking via external AI sidecar

### API Layer

All API controllers extend `BaseApiController` which provides standardized responses:
```php
$this->success($data, $message = 'OK', $code = 200);
$this->error($message, $code = 400, $errors = []);
```

**Critical pattern**: All endpoints are user-scoped. Always filter by `auth()->id()`:
```php
Account::where('user_id', auth()->id())->get();
```

Routes are in `routes/api.php` with 36+ controllers in `app/Http/Controllers/Api/V1/`.

### WhatsApp AI Flow

```
POST /api/webhooks/whatsapp
    → WebhookController
    → MessageHandler::handleIncoming()
    → ProcessAiMessage job (queued)
    → AiAgentService::processMessage()
    → HTTP POST to sidecar (http://127.0.0.1:8101/ask)
    → Response sanitized for WhatsApp
    → WhatsAppService sends reply
```

The AI sidecar is an external service that:
- Handles per-phone session locking (returns 429 if busy)
- Expects: `{ phone_number, system_prompt, prompt, max_chars }`
- Returns: `{ success: true, data: { answer: "..." } }`

### Key Services

| Service | Purpose |
|---------|---------|
| `app/Services/WhatsApp/AiAgentService.php` | AI orchestration, prompt building |
| `app/Services/WhatsApp/MessageHandler.php` | Routes incoming WhatsApp messages |
| `app/Services/WhatsApp/ConversationManager.php` | Session state management |
| `app/Services/FcmService.php` | Firebase push notifications |

### Database Schema (52+ migrations)

Core banking tables:
- `users` - Extended with `pin_hash`, `transaction_pin_hash`, `biometric_enabled`
- `accounts` - Types: current, savings, fixed_deposit
- `transactions` - Credit/debit with `balance_after`
- `transfers` - Supports own, same_bank, interbank, SWIFT, mobile_wallet, bulk
- `beneficiaries`, `cards`, `loans`, `loan_products`, `loan_repayment_schedules`

Corporate banking:
- `corporate_accounts`, `corporate_users`, `approval_workflows`, `transfer_approvals`

WhatsApp/legacy (from restaurant system):
- `guests`, `guest_conversations`, `whatsapp_sessions`

### User Roles

- **admin** - Full settings access
- **manager** - Restricted settings access
- **customer** - API access only (default)

Web uses `role:admin,manager` middleware. API uses `auth:sanctum`.

## Key Configuration

### Required Environment Variables
```env
APP_KEY=
DB_CONNECTION=sqlite|mysql|pgsql
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_VERIFY_TOKEN=
FIREBASE_CREDENTIALS=
```

### Test Data

Seeders create 5 users, 2 accounts per customer, sample transactions/transfers/loans/cards.
Check `database/seeders/BankingUsersSeeder.php` for credentials (password: `password`).

## Historical Context

This project evolved from a restaurant/hospitality system. Legacy artifacts remain:
- `Guest` model for WhatsApp users (not banking customers)
- `guest_conversations` table for chat history
- Restaurant-related prompts in `AiAgentService.php` (partially stubbed)
