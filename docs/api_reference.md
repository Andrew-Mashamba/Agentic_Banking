# Banking API Reference (v1)

All endpoints are under **`/api/v1`**. Require **Bearer token** (Laravel Sanctum).  
The AI should call these when fulfilling client requests from WhatsApp.

Base URL: `{APP_URL}/api/v1`

---

## Authentication

Send the user's API token in the header:
```
Authorization: Bearer {token}
```

To obtain a token for a user (e.g. when linking WhatsApp to a user):
```http
POST /login
Content-Type: application/json
{"email":"...","password":"..."}
```
Then create a token via `$user->createToken('whatsapp')->plainTextToken` (server-side).

---

## Endpoints

### Profile (F1.9)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/profile` | Current user profile |
| PUT | `/profile` | Update name, phone_number |

### KYC (F1.7, F1.8)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kyc` | List KYC submissions |
| POST | `/kyc` | Create submission (status: draft\|pending) |
| GET | `/kyc/{id}` | Submission + documents |
| POST | `/kyc/{id}/documents` | Add document (document_type, file_path) |

### Trusted devices (F1.5, F1.6, F12.7)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/trusted-devices` | List trusted devices |
| POST | `/trusted-devices` | Register (device_identifier, name) |
| DELETE | `/trusted-devices/{id}` | Remove device |

### Accounts (F2)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/accounts/summary` | Consolidated balance & account list |
| GET | `/accounts` | List accounts (optional: ?type=, ?status=) |
| GET | `/accounts/{id}` | Account detail + recent transactions |
| PUT | `/accounts/{id}` | Update nickname, set primary (is_primary) |
| GET | `/upcoming-payments` | Scheduled + recurring payments (F2.11) |

### Transactions (F2.3, F2.4)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/transactions` | List (optional: account_id, type, from_date, to_date, per_page) |
| GET | `/transactions/{id}` | Transaction detail |

### Beneficiaries (F3.8)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/beneficiaries` | List (optional: ?type=) |
| POST | `/beneficiaries` | Create (name, type, account_number, bank_*, mobile_*) |
| GET | `/beneficiaries/{id}` | Show |
| PUT | `/beneficiaries/{id}` | Update |
| DELETE | `/beneficiaries/{id}` | Delete |

### Transfers (F3)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/transfers` | List (optional: status, account_id, per_page) |
| POST | `/transfers` | Create (from_account_id, to_account_id|beneficiary_id, amount, type, reference, scheduled_at, swift_details) |
| GET | `/transfers/{id}` | Show |
| GET/POST/GET/PUT/DELETE | `/recurring-transfers` | Recurring transfers (F3.7): from_account_id, beneficiary_id, amount, frequency, end_at |
| GET/POST/GET | `/bulk-payments` | Bulk payment batches + items (F3.10): name, items[{beneficiary_id, amount}] |
| GET/POST/GET | `/cardless-withdrawals` | Cardless ATM (F3.15): account_id, amount → code + expires_at |
| POST | `/loan-repayments` | Repay loan (F3.16): loan_id, amount, account_id |

### Cards (F4)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/cards` | List cards |
| GET | `/cards/{id}` | Card detail |
| PUT | `/cards/{id}` | Update (status, daily_limit_amount, online_enabled, international_enabled) |
| POST | `/cards/{id}/freeze` | Freeze card |
| POST | `/cards/{id}/unfreeze` | Unfreeze card |
| POST | `/cards/{id}/block` | Block card |
| GET/PUT | `/cards/{id}/spending-limits` | Category limits (F4.3): limits[{category, limit_amount}] (online, international, pos, atm) |

### Loans (F5)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/loan-products` | List active loan products |
| GET | `/loans` | List user loans (optional: ?status=) |
| GET | `/loans/{id}` | Loan detail + product + account |
| GET | `/loans/{id}/repayment-schedule` | Repayment schedule |
| GET | `/loan-applications` | List applications |
| POST | `/loan-applications` | Submit (loan_product_id, amount_requested, tenor_months, purpose) |
| GET | `/loan-applications/{id}` | Application detail |
| GET/POST | `/loans/{id}/early-settlement` | Early settlement requests (F5.5) |
| POST | `/loans/early-settlement` | Request early settlement (loan_id) |
| POST | `/loans/topup` | Loan top-up request (F5.6): loan_id, amount_requested |

### Savings & Investments (F6)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/GET | `/fixed-deposits` | List, create (account_id, amount, tenor_months), show |
| POST | `/fixed-deposits/{id}/break` | Request break FD (F6.3) |
| GET | `/investment-products` | List products |
| GET/POST/GET | `/investments` | List, create (investment_product_id, amount), show |
| GET | `/securities` | Securities holdings (F6.7) |
| GET | `/dividends` | Dividend history (F6.9) |
| GET/PUT | `/dividend-reinvestment` | Get/update reinvestment (enabled, investable_type, investable_id) (F6.10) |

### Wallet & digital (F7)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/GET | `/gift-cards` | Gift cards (F7.3): amount, recipient_email, recipient_phone |
| GET/POST/GET | `/money-requests` | Request money (F7.4): recipient_id, amount, message; POST …/respond (action: accept\|reject) |
| GET/POST/GET | `/bill-splits` | Bill splits (F7.5): total_amount, description, members[{user_id, amount_owed}] |

### Notifications (F8)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notification-preferences` | List preferences by channel |
| PUT | `/notification-preferences` | Update (channel: push\|sms\|email, transaction_alerts, low_balance_alerts, low_balance_threshold) |

### Reports (F9)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reports/custom` | Custom report (account_id?, from, to) → transactions |
| GET | `/reports/tax` | Tax report (year) |
| GET | `/reports/interest-certificate` | Interest certificate (year) |

### Service requests (F10)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/GET | `/service-requests` | List, create (type: cheque_book\|stop_cheque\|account_opening\|address_update\|dormant_reactivation, details?), show |

### Support (F11)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/support-tickets` | List tickets |
| POST | `/support-tickets` | Create (subject) |
| GET | `/support-tickets/{id}` | Ticket + messages |

### Branches, ATMs, Appointments, FAQs (F11.3–F11.5)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/branches` | List branches |
| GET | `/branches/{id}` | Branch detail |
| GET | `/atms` | List ATMs (optional: lat, lng for distance) |
| GET | `/atms/{id}` | ATM detail |
| GET/POST/GET/DELETE | `/appointments` | List, create (type: branch\|callback, branch_id?, scheduled_at, notes), show, cancel |
| GET | `/faqs` | List FAQs (optional: ?category=) |

### Security (F12)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/security/login-history` | Recent logins (F12.6) |
| GET/PUT | `/security/transaction-limits` | Get/update per_transaction_limit, daily_limit (F12.4) |
| POST | `/security/fraud-report` | Report fraud (description) (F12.8) |
| POST | `/security/change-password` | current_password, password, password_confirmation (F12.1) |
| POST | `/security/change-pin` | current_pin, pin, pin_confirmation (F12.2) |
| POST | `/security/biometric` | enabled (boolean) (F12.3) |

### Trade & Corporate (F13, F14)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/GET | `/letter-of-credit` | LC applications (corporate_account_id, amount, currency?, details?) |
| GET/POST/GET | `/bank-guarantees` | Bank guarantee requests (same shape) |
| GET/POST/GET | `/forex-bookings` | Forex (corporate_account_id, from_currency, to_currency, amount, rate) |
| GET | `/corporate/accounts` | Owned + member corporate accounts |
| GET/POST | `/corporate/accounts/{id}/sub-users` | List sub-users; add (user_id, role) – primary only (F14.1) |
| GET | `/corporate/accounts/{id}/approval-workflows` | Workflows (F14.3) |
| GET | `/corporate/accounts/{id}/approval-rights` | User approval rights (F14.2) |
| POST | `/corporate/approval-rights` | Assign (corporate_account_id, corporate_user_id, approval_workflow_id, max_approvable_amount?) |

---

## Response format

Success:
```json
{"status":"success","message":"OK","data":{...}}
```

Error:
```json
{"status":"error","message":"...","errors":{...}}
```

HTTP codes: 200 OK, 201 Created, 400 Bad Request, 403 Forbidden, 404 Not Found, 422 Validation Error.

---

## AI usage

When a WhatsApp message requests balance, transfer, loans, etc.:

1. Resolve the sender to a **User** (and optionally create/link a Sanctum token).
2. Call the appropriate **GET** endpoint to read data and reply with a summary.
3. For actions (e.g. create transfer, apply for loan), call the **POST** endpoint with validated input, then confirm to the user.

All endpoints are scoped to the authenticated user; no need to pass user_id in the body.
