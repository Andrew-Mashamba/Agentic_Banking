# Core Banking System – Features List (Implementation Ready)

Single feature list for client activities. Each item is a discrete deliverable. No distinction between mobile and internet banking—one unified client experience.

---

## Client Activities

### 1. Onboarding & Access

| ID | Feature | Description |
|----|---------|-------------|
| F1.1 | Self-registration | User can self-register and complete account activation flow |
| F1.2 | Login (PIN) | Login using PIN |
| F1.3 | Login (password) | Login using password |
| F1.4 | Login (biometrics) | Login using Face ID / fingerprint |
| F1.5 | Device binding | Bind device to account; enforce trusted device rules |
| F1.6 | Trusted device management | Add, remove, list trusted devices |
| F1.7 | KYC submission | Submit KYC information and required documents |
| F1.8 | Document upload | Upload ID, proof of address, and other KYC documents |
| F1.9 | Profile setup | Complete and edit profile (name, contact, preferences) |

### 2. Account Management & Dashboard

| ID | Feature | Description |
|----|---------|-------------|
| F2.1 | View account balances | View current, savings, and fixed deposit balances (consolidated summary) |
| F2.2 | Net worth view | Aggregated net worth across all products |
| F2.3 | Mini statement | View recent transactions (e.g. last N or last 7 days) |
| F2.4 | Transaction history (filters) | View history with filters by date range and transaction type |
| F2.5 | Download bank statement (PDF) | Generate and download statement as PDF (any date range) |
| F2.6 | Email bank statement | Email statement (PDF) to user’s email |
| F2.7 | Export statements/reports (CSV/Excel) | Export transactions and reports to CSV/Excel |
| F2.8 | Account nickname | Set custom nickname for an account |
| F2.9 | Set primary account | Set default/primary account for transfers and display |
| F2.10 | Spending analysis (charts) | Graphical spending/income analysis |
| F2.11 | Upcoming payments | List of scheduled and recurring payments |

### 3. Transfers & Payments

| ID | Feature | Description |
|----|---------|-------------|
| F3.1 | Own-account transfer | Transfer between user’s own accounts |
| F3.2 | Same-bank transfer | Transfer to another account at same bank |
| F3.3 | Interbank transfer (local) | Transfer via local clearing / RTGS / EFT |
| F3.4 | International transfer (SWIFT) | Send SWIFT/international transfer with full remittance details |
| F3.5 | Mobile wallet transfer | Transfer to M-Pesa, Airtel Money, etc. |
| F3.6 | Scheduled transfer | Schedule a one-off future-dated transfer |
| F3.7 | Recurring transfer / standing orders | Set up recurring transfer or standing order (frequency, amount, end date) |
| F3.8 | Save beneficiaries | Add, edit, delete saved beneficiaries |
| F3.9 | Bulk beneficiary upload | Upload multiple beneficiaries (CSV/file) |
| F3.10 | Bulk payments (salary / payroll) | Upload and process bulk salary or CSV payroll payments |
| F3.11 | Multi-level approval (business) | Workflow with multiple approvers for business users |
| F3.12 | Utility payments | Pay electricity, water, TV, etc. |
| F3.13 | Bill payments | Pay government bills, school fees, other bills |
| F3.14 | Merchant payments (QR/TANQR) | Pay merchant via QR code or TANQR |
| F3.15 | Cardless ATM withdrawal | Initiate and complete cardless cash withdrawal |
| F3.16 | Loan repayment | Repay loan (choose loan, amount, account) |
| F3.17 | Tax payments | Pay tax obligations |
| F3.18 | Insurance premium payments | Pay insurance premiums |

### 4. Cards Management

| ID | Feature | Description |
|----|---------|-------------|
| F4.1 | View card details (masked) | View masked card number, expiry, type |
| F4.2 | Freeze/unfreeze card | Temporarily freeze or unfreeze card |
| F4.3 | Set card limits | Set daily/transaction limits and spending category controls (e.g. online, international) |
| F4.4 | Change card PIN | Change ATM/transaction PIN for card |
| F4.5 | Activate new card | Activate newly issued card |
| F4.6 | Block lost/stolen card | Report and block lost or stolen card |
| F4.7 | Virtual card generation | Generate and manage virtual card(s) |
| F4.8 | Online/international toggle | Enable/disable card for online and international payments |

### 5. Loans & Credit

| ID | Feature | Description |
|----|---------|-------------|
| F5.1 | Apply for loan | Submit loan application with amount, tenor, purpose |
| F5.2 | Check loan eligibility | Pre-check eligibility (amount, rate, tenure) |
| F5.3 | View loan balance | View outstanding balance per loan |
| F5.4 | View repayment schedule | View instalments and due dates |
| F5.5 | Early settlement request | Request early loan payoff and see settlement amount |
| F5.6 | Top-up request | Request loan top-up (if product allows) |
| F5.7 | Micro-loan (instant) | Apply and receive instant micro-loan |

### 6. Savings & Investments

| ID | Feature | Description |
|----|---------|-------------|
| F6.1 | Open savings account | Open new savings account |
| F6.2 | Open fixed deposit | Open fixed deposit with amount and tenor |
| F6.3 | Break fixed deposit | Request early break with applicable terms |
| F6.4 | View interest earned | View interest earned on savings and FDs |
| F6.5 | Invest in T-bills/bonds | Subscribe to treasury bills and bonds |
| F6.6 | Mutual fund subscription | Subscribe to mutual funds |
| F6.7 | Securities trading | Place and manage securities trades |
| F6.8 | Portfolio analysis | View and analyse portfolio (holdings, P&L) |
| F6.9 | Dividend tracking | View and track dividend history |
| F6.10 | Dividend reinvestment | Set up and manage dividend reinvestment |

### 7. Wallet & Digital Services

| ID | Feature | Description |
|----|---------|-------------|
| F7.1 | Airtime purchase | Buy airtime for self or others |
| F7.2 | Data bundle purchase | Purchase data bundles |
| F7.3 | Gift cards | Buy and send gift cards |
| F7.4 | Request money | Request money from another user |
| F7.5 | Split bills | Split a bill with other users |
| F7.6 | QR code payments | Pay via QR code scan |
| F7.7 | NFC tap payments | Pay via NFC (tap) where supported |

### 8. Alerts & Notifications

| ID | Feature | Description |
|----|---------|-------------|
| F8.1 | Real-time transaction alerts | Alert on each transaction |
| F8.2 | Push notifications | Configurable push notifications |
| F8.3 | SMS/email preferences | Manage channels and frequency (SMS, email) |
| F8.4 | Low balance alerts | Alert when balance falls below threshold |

### 9. Statements & Reports

| ID | Feature | Description |
|----|---------|-------------|
| F9.1 | Custom report generation | Build and generate custom date/account reports |
| F9.2 | Tax reports | Generate tax-year or custom tax reports |
| F9.3 | Interest certificates | Generate interest earned certificates |

### 10. Service Requests

| ID | Feature | Description |
|----|---------|-------------|
| F10.1 | Cheque book request | Request new cheque book |
| F10.2 | Stop cheque request | Request stop cheque |
| F10.3 | Account opening request | Submit request to open new account |
| F10.4 | Address update | Submit and track address change |
| F10.5 | Dormant account reactivation | Request reactivation of dormant account |

### 11. Customer Support

| ID | Feature | Description |
|----|---------|-------------|
| F11.1 | In-app chat | Live chat or chatbot support |
| F11.2 | Raise support ticket | Create and track support ticket |
| F11.3 | Locate branches/ATMs | Find branches and ATMs (map/list) |
| F11.4 | Book appointment | Book branch or call-back appointment |
| F11.5 | FAQs | Searchable FAQs |
| F11.6 | Call support | One-tap call to support number |

### 12. Security & Settings

| ID | Feature | Description |
|----|---------|-------------|
| F12.1 | Change password | Change login password |
| F12.2 | Change PIN | Change transaction/PIN |
| F12.3 | Biometric enable/disable | Turn biometric login on/off |
| F12.4 | Transaction limit settings | Set per-transaction or daily limits |
| F12.5 | 2FA setup | Enable/disable and manage 2FA |
| F12.6 | Login history | View recent login history |
| F12.7 | Device management | View and revoke registered devices |
| F12.8 | Report fraud | Report suspected fraud and block/alert |

### 13. Trade & Corporate (Business Users)

| ID | Feature | Description |
|----|---------|-------------|
| F13.1 | Letter of Credit application | Apply for LC |
| F13.2 | Bank guarantee requests | Request and track bank guarantees |
| F13.3 | Forex booking | Book foreign exchange |
| F13.4 | Corporate user role management | Manage sub-users and roles |

### 14. Corporate Admin & Approval (Business Users)

| ID | Feature | Description |
|----|---------|-------------|
| F14.1 | Add sub-users | Add sub-users under corporate account |
| F14.2 | Assign approval rights | Assign who can approve what (amounts, types) |
| F14.3 | Transaction authorization workflows | Multi-step approval (e.g. maker-checker, dual control) |

---

## Implementation Notes

- **Single channel:** All features (F1–F14) are client activities delivered through one system (no separate mobile vs internet banking lists).
- **Dependencies:** Onboarding (F1) and Security (F12) are foundational; Account Management & Dashboard (F2) and basic Transfers (F3.1–F3.5) are typical Phase 1.
- **Prioritisation:** Use IDs (e.g. F1.1, F3.10) in backlog/tickets; add Priority, Phase, and API/UI subtasks as needed.
