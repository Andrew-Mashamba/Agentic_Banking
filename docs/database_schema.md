# Core Banking System – Database Schema

Schema supports all client activities (F1–F14). Default database: SQLite (Laravel default).

---

## Table Overview by Feature

| Feature area | Tables |
|--------------|--------|
| F1 Onboarding & Access | users (extended), trusted_devices, kyc_submissions, kyc_documents |
| F2 Account & Dashboard | accounts, transactions |
| F3 Transfers & Payments | beneficiaries, transfers, recurring_transfers, bulk_payment_batches, bulk_payment_items, transfer_approvals, cardless_withdrawals |
| F4 Cards | cards, card_spending_limits |
| F5 Loans | loan_products, loans, loan_repayment_schedules, loan_repayments, loan_applications, early_settlement_requests, loan_topup_requests |
| F6 Savings & Investments | fixed_deposits, investment_products, investments, securities_holdings, securities_trades, dividends, dividend_reinvestment_settings |
| F7 Wallet & Digital | gift_cards, money_requests, bill_splits, bill_split_members |
| F8 Alerts | notification_preferences |
| F9 Statements & Reports | (uses accounts, transactions) |
| F10 Service Requests | service_requests |
| F11 Customer Support | support_tickets, support_ticket_messages, branches, atms, appointments, faqs |
| F12 Security | login_history, user_transaction_limits, fraud_reports; users (pin, 2FA, etc.) |
| F13 Trade & Corporate | corporate_accounts, corporate_users, letter_of_credit_applications, bank_guarantee_requests, forex_bookings |
| F14 Corporate Approval | approval_workflows, approval_workflow_steps, user_approval_rights |

---

## Existing Tables (unchanged)

- users, password_reset_tokens, sessions
- cache, jobs, personal_access_tokens
- guests, whatsapp_sessions, guest_conversations
- settings, notifications, error_logs, device_tokens
- two_factor_columns, phone_number, status on users

---

## New Tables (detailed)

### Users extension (F1, F12)
- **users**: add `pin_hash`, `transaction_pin_hash`, `biometric_enabled`, `primary_account_id` (FK accounts).

### F1 Onboarding & Access
- **trusted_devices**: user_id, device_identifier, name, last_used_at, trusted_at.
- **kyc_submissions**: user_id, status (draft/pending/approved/rejected), submitted_at.
- **kyc_documents**: kyc_submission_id, document_type, file_path, verified_at.

### F2 Account Management & Dashboard
- **accounts**: user_id, type (current/savings/fixed_deposit), account_number (unique), currency, balance, nickname, is_primary, status, opened_at.
- **transactions**: account_id, type (credit/debit), amount, balance_after, reference, description, meta (JSON), created_at.

### F3 Transfers & Payments
- **beneficiaries**: user_id, name, account_number, bank_code, bank_name, type (same_bank/interbank/mobile_wallet/international), mobile_wallet_provider, is_verified.
- **transfers**: from_account_id, to_account_id (nullable), beneficiary_id (nullable), amount, currency, type (own/same_bank/interbank/swift/mobile_wallet/utility/bill/merchant/cardless/loan/tax/insurance/bulk), status, reference, swift_details (JSON), scheduled_at, executed_at, approval_workflow_id (nullable).
- **recurring_transfers**: user_id, from_account_id, beneficiary_id, amount, frequency (daily/weekly/monthly), next_run_at, end_at, last_run_at, status.
- **bulk_payment_batches**: user_id, name, total_amount, total_count, status, processed_at.
- **bulk_payment_items**: bulk_payment_batch_id, beneficiary_id, amount, status, transfer_id (nullable).
- **transfer_approvals**: transfer_id, approver_user_id, level, status (pending/approved/rejected), acted_at.
- **cardless_withdrawals**: user_id, account_id, amount, code, expires_at, status, used_at.

### F4 Cards
- **cards**: user_id, account_id, last_four, expiry_date, type (physical/virtual), status (pending_activation/active/frozen/blocked), pin_set_at, daily_limit_amount, online_enabled, international_enabled, created_at.
- **card_spending_limits**: card_id, category (online/international/pos/atm), limit_amount.

### F5 Loans
- **loan_products**: name, min_amount, max_amount, interest_rate, tenor_months, eligibility_rules (JSON).
- **loans**: user_id, account_id (disbursement), loan_product_id, amount, outstanding_balance, interest_rate, status, disbursed_at, maturity_date.
- **loan_repayment_schedules**: loan_id, due_date, principal_amount, interest_amount, status, paid_at.
- **loan_repayments**: loan_id, amount, paid_at, transaction_id (nullable).
- **loan_applications**: user_id, loan_product_id, amount_requested, tenor_months, purpose, status, eligibility_result (JSON), approved_at.
- **early_settlement_requests**: loan_id, requested_at, settlement_amount, status, processed_at.
- **loan_topup_requests**: loan_id, amount_requested, status, processed_at.

### F6 Savings & Investments
- **fixed_deposits**: account_id, amount, tenor_months, interest_rate, maturity_date, status, break_requested_at.
- **investment_products**: type (tbills/bond/mutual_fund), name, code, min_amount.
- **investments**: user_id, investment_product_id, amount, units, status, opened_at.
- **securities_holdings**: user_id, symbol, name, quantity, average_cost.
- **securities_trades**: user_id, symbol, side (buy/sell), quantity, price, trade_at.
- **dividends**: investable_type, investable_id (polymorphic or investment_id), amount, paid_at.
- **dividend_reinvestment_settings**: user_id, investable_type, investable_id, enabled.

### F7 Wallet & Digital
- **gift_cards**: purchaser_id, recipient_phone, recipient_email, amount, currency, code, status, redeemed_at.
- **money_requests**: from_user_id, to_user_id, amount, status, message, responded_at.
- **bill_splits**: created_by_id, total_amount, description, status, settled_at.
- **bill_split_members**: bill_split_id, user_id, amount_owed, paid_at.

### F8 Alerts
- **notification_preferences**: user_id, channel (push/sms/email), transaction_alerts, low_balance_alerts, low_balance_threshold.

### F10 Service Requests
- **service_requests**: user_id, type (cheque_book/stop_cheque/account_opening/address_update/dormant_reactivation), status, details (JSON), resolved_at.

### F11 Customer Support
- **support_tickets**: user_id, subject, status, created_at.
- **support_ticket_messages**: support_ticket_id, user_id (nullable), is_staff, body, created_at.
- **branches**: name, address, latitude, longitude, phone, opening_hours (JSON).
- **atms**: branch_id (nullable), address, latitude, longitude, status.
- **appointments**: user_id, branch_id, type (branch/callback), scheduled_at, status.
- **faqs**: category, question, answer, sort_order.

### F12 Security
- **login_history**: user_id, ip_address, user_agent, logged_at.
- **user_transaction_limits**: user_id, limit_type (daily/per_transaction), amount.
- **fraud_reports**: user_id, description, status, reported_at, resolved_at.

### F13 & F14 Corporate
- **corporate_accounts**: primary_user_id, company_name, registration_number, status.
- **corporate_users**: corporate_account_id, user_id, role, status.
- **letter_of_credit_applications**: corporate_user_id, amount, currency, details (JSON), status.
- **bank_guarantee_requests**: corporate_user_id, amount, details (JSON), status.
- **forex_bookings**: corporate_user_id, from_currency, to_currency, amount, rate, status, booked_at.
- **approval_workflows**: corporate_account_id, name, type (transfer/bulk), is_active.
- **approval_workflow_steps**: approval_workflow_id, step_order, role_required, min_amount, max_amount.
- **user_approval_rights**: corporate_user_id, approval_workflow_id, max_approvable_amount.

---

## Migration Order

Migrations are named `2026_02_22_100001_` through `2026_02_22_100052_` and run in order so that foreign keys resolve.

**Apply schema:**
```bash
# If you haven't already
composer install
cp .env.example .env   # or use the provided .env
php artisan key:generate

# Default DB is SQLite; ensure the file exists
touch database/database.sqlite

# Run all migrations
php artisan migrate
```

**.env:** The project includes a `.env` file configured for Laravel’s default database (SQLite, `database/database.sqlite`). For MySQL/PostgreSQL, set `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (and optionally `DB_HOST`, `DB_PORT`) in `.env` and create the database first.
