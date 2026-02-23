<?php

use App\Http\Controllers\WhatsApp\WebhookController;
use App\Http\Controllers\WhatsApp\FlowDataEndpointController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\BeneficiaryController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\CardController;
use App\Http\Controllers\Api\V1\CardSpendingLimitController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\LoanApplicationController;
use App\Http\Controllers\Api\V1\LoanRepaymentController;
use App\Http\Controllers\Api\V1\LoanEarlySettlementController;
use App\Http\Controllers\Api\V1\LoanTopupController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\TrustedDeviceController;
use App\Http\Controllers\Api\V1\UpcomingPaymentsController;
use App\Http\Controllers\Api\V1\RecurringTransferController;
use App\Http\Controllers\Api\V1\BulkPaymentController;
use App\Http\Controllers\Api\V1\CardlessWithdrawalController;
use App\Http\Controllers\Api\V1\FixedDepositController;
use App\Http\Controllers\Api\V1\InvestmentController;
use App\Http\Controllers\Api\V1\GiftCardController;
use App\Http\Controllers\Api\V1\MoneyRequestController;
use App\Http\Controllers\Api\V1\BillSplitController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use App\Http\Controllers\Api\V1\AtmController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\LetterOfCreditController;
use App\Http\Controllers\Api\V1\BankGuaranteeController;
use App\Http\Controllers\Api\V1\ForexBookingController;
use App\Http\Controllers\Api\V1\CorporateController;
use App\Http\Controllers\Api\Internal\SensitiveActionController;
use Illuminate\Support\Facades\Route;

// WhatsApp webhook (no auth) - keep at root for Meta verification
Route::prefix('webhooks')->group(function () {
    Route::get('whatsapp', [WebhookController::class, 'verify']);
    Route::post('whatsapp', [WebhookController::class, 'handle']);
    // Flows data endpoint (Meta POSTs here for dynamic flow data; must be public HTTPS)
    Route::post('whatsapp-flows/data', [FlowDataEndpointController::class, 'handle']);
});

// ══════════════════════════════════════════════════════════════════════
// INTERNAL API - No auth, for AI Agent only (localhost access)
// The AI agent calls these endpoints via tinker/HTTP to serve WhatsApp clients.
// User context is passed via X-User-Id header.
// ══════════════════════════════════════════════════════════════════════
Route::prefix('internal')->middleware('api.internal')->group(function () {
    // Accounts
    Route::get('accounts/summary', [AccountController::class, 'summary']);
    Route::get('accounts', [AccountController::class, 'index']);
    Route::get('accounts/{account}', [AccountController::class, 'show']);

    // Transactions
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);

    // Beneficiaries
    Route::get('beneficiaries', [BeneficiaryController::class, 'index']);
    Route::post('beneficiaries', [BeneficiaryController::class, 'store']);

    // Transfers
    Route::get('transfers', [TransferController::class, 'index']);
    Route::post('transfers', [TransferController::class, 'store']);
    Route::get('transfers/{transfer}', [TransferController::class, 'show']);

    // Cards
    Route::get('cards', [CardController::class, 'index']);
    Route::get('cards/{card}', [CardController::class, 'show']);
    Route::post('cards/{card}/freeze', [CardController::class, 'freeze']);
    Route::post('cards/{card}/unfreeze', [CardController::class, 'unfreeze']);
    Route::post('cards/{card}/block', [CardController::class, 'block']);

    // Loans & loan applications (AI can submit on behalf of client)
    Route::get('loan-products', [LoanController::class, 'products']);
    Route::get('loans', [LoanController::class, 'index']);
    Route::get('loans/{loan}', [LoanController::class, 'show']);
    Route::get('loans/{loan}/repayment-schedule', [LoanController::class, 'repaymentSchedule']);
    Route::get('loan-applications', [LoanApplicationController::class, 'index']);
    Route::post('loan-applications', [LoanApplicationController::class, 'store']);
    Route::get('loan-applications/{loanApplication}', [LoanApplicationController::class, 'show']);

    // Fixed deposits & investments (read-only)
    Route::get('fixed-deposits', [FixedDepositController::class, 'index']);
    Route::get('investment-products', [InvestmentController::class, 'products']);
    Route::get('investments', [InvestmentController::class, 'index']);

    // Cardless ATM withdrawals (AI can generate on behalf of client)
    Route::get('cardless-withdrawals', [CardlessWithdrawalController::class, 'index']);
    Route::post('cardless-withdrawals', [CardlessWithdrawalController::class, 'store']);
    Route::get('cardless-withdrawals/{id}', [CardlessWithdrawalController::class, 'show']);

    // Support (public info)
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{id}', [BranchController::class, 'show']);
    Route::get('atms', [AtmController::class, 'index']);
    Route::get('faqs', [FaqController::class, 'index']);

    // Sensitive actions (OTP-gated for WhatsApp AI)
    Route::post('sensitive-action-request', [SensitiveActionController::class, 'request']);
    Route::post('sensitive-action-confirm', [SensitiveActionController::class, 'confirm']);

    // Send WhatsApp Flow to user (AI calls this to show transfer/loan/card flow)
    Route::post('send-flow', [\App\Http\Controllers\Api\Internal\SendFlowController::class, 'send']);

    // Long-term memory and pending tasks (AI identity, resume after disconnect)
    Route::get('user-memory', [\App\Http\Controllers\Api\Internal\UserMemoryController::class, 'show']);
    Route::post('user-memory', [\App\Http\Controllers\Api\Internal\UserMemoryController::class, 'append']);
    Route::get('pending-tasks', [\App\Http\Controllers\Api\Internal\PendingTaskController::class, 'index']);
    Route::post('pending-tasks', [\App\Http\Controllers\Api\Internal\PendingTaskController::class, 'store']);
    Route::post('pending-tasks/cancel-all', [\App\Http\Controllers\Api\Internal\PendingTaskController::class, 'cancelAll']);
    Route::post('pending-tasks/{id}/complete', [\App\Http\Controllers\Api\Internal\PendingTaskController::class, 'complete']);

    // WhatsApp data export (GDPR portability; X-User-Id)
    Route::get('whatsapp-data-export', [\App\Http\Controllers\Api\Internal\WhatsAppDataExportController::class, '__invoke']);
});

// Banking API v1 – for AI and clients (auth:sanctum = Bearer token)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Profile (F1.9)
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);

    // KYC (F1.7, F1.8)
    Route::get('kyc', [KycController::class, 'index']);
    Route::post('kyc', [KycController::class, 'store']);
    Route::get('kyc/{id}', [KycController::class, 'show']);
    Route::post('kyc/{id}/documents', [KycController::class, 'addDocument']);

    // Trusted devices (F1.5, F1.6, F12.7)
    Route::get('trusted-devices', [TrustedDeviceController::class, 'index']);
    Route::post('trusted-devices', [TrustedDeviceController::class, 'store']);
    Route::delete('trusted-devices/{id}', [TrustedDeviceController::class, 'destroy']);

    // Accounts & dashboard (F2)
    Route::get('accounts/summary', [AccountController::class, 'summary']);
    Route::apiResource('accounts', AccountController::class)->only(['index', 'show', 'update']);
    Route::get('upcoming-payments', [UpcomingPaymentsController::class, 'index']);

    // Transactions (F2.3, F2.4)
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);

    // Beneficiaries (F3.8)
    Route::apiResource('beneficiaries', BeneficiaryController::class);

    // Transfers (F3.1–F3.18)
    Route::apiResource('transfers', TransferController::class)->only(['index', 'store', 'show']);
    Route::apiResource('recurring-transfers', RecurringTransferController::class);
    Route::get('bulk-payments', [BulkPaymentController::class, 'index']);
    Route::post('bulk-payments', [BulkPaymentController::class, 'store']);
    Route::get('bulk-payments/{id}', [BulkPaymentController::class, 'show']);
    Route::get('cardless-withdrawals', [CardlessWithdrawalController::class, 'index']);
    Route::post('cardless-withdrawals', [CardlessWithdrawalController::class, 'store']);
    Route::get('cardless-withdrawals/{id}', [CardlessWithdrawalController::class, 'show']);
    Route::post('loan-repayments', [LoanRepaymentController::class, 'store']);

    // Cards (F4)
    Route::get('cards', [CardController::class, 'index']);
    Route::get('cards/{card}', [CardController::class, 'show']);
    Route::put('cards/{card}', [CardController::class, 'update']);
    Route::post('cards/{card}/freeze', [CardController::class, 'freeze']);
    Route::post('cards/{card}/unfreeze', [CardController::class, 'unfreeze']);
    Route::post('cards/{card}/block', [CardController::class, 'block']);
    Route::get('cards/{card}/spending-limits', [CardSpendingLimitController::class, 'index']);
    Route::put('cards/{card}/spending-limits', [CardSpendingLimitController::class, 'update']);

    // Loans (F5)
    Route::get('loan-products', [LoanController::class, 'products']);
    Route::get('loans', [LoanController::class, 'index']);
    Route::get('loans/{loan}', [LoanController::class, 'show']);
    Route::get('loans/{loan}/repayment-schedule', [LoanController::class, 'repaymentSchedule']);
    Route::get('loans/{loanId}/early-settlement', [LoanEarlySettlementController::class, 'index']);
    Route::post('loans/early-settlement', [LoanEarlySettlementController::class, 'store']);
    Route::post('loans/topup', [LoanTopupController::class, 'store']);
    Route::get('loan-applications', [LoanApplicationController::class, 'index']);
    Route::post('loan-applications', [LoanApplicationController::class, 'store']);
    Route::get('loan-applications/{loanApplication}', [LoanApplicationController::class, 'show']);

    // Savings & Investments (F6)
    Route::get('fixed-deposits', [FixedDepositController::class, 'index']);
    Route::post('fixed-deposits', [FixedDepositController::class, 'store']);
    Route::get('fixed-deposits/{id}', [FixedDepositController::class, 'show']);
    Route::post('fixed-deposits/{id}/break', [FixedDepositController::class, 'breakRequest']);
    Route::get('investment-products', [InvestmentController::class, 'products']);
    Route::get('investments', [InvestmentController::class, 'index']);
    Route::post('investments', [InvestmentController::class, 'store']);
    Route::get('investments/{id}', [InvestmentController::class, 'show']);
    Route::get('securities', [InvestmentController::class, 'securities']);
    Route::get('dividends', [InvestmentController::class, 'dividends']);
    Route::get('dividend-reinvestment', [InvestmentController::class, 'dividendReinvestment']);
    Route::put('dividend-reinvestment', [InvestmentController::class, 'updateDividendReinvestment']);

    // Wallet & digital (F7)
    Route::get('gift-cards', [GiftCardController::class, 'index']);
    Route::post('gift-cards', [GiftCardController::class, 'store']);
    Route::get('gift-cards/{id}', [GiftCardController::class, 'show']);
    Route::get('money-requests', [MoneyRequestController::class, 'index']);
    Route::post('money-requests', [MoneyRequestController::class, 'store']);
    Route::get('money-requests/{id}', [MoneyRequestController::class, 'show']);
    Route::post('money-requests/{id}/respond', [MoneyRequestController::class, 'respond']);
    Route::get('bill-splits', [BillSplitController::class, 'index']);
    Route::post('bill-splits', [BillSplitController::class, 'store']);
    Route::get('bill-splits/{id}', [BillSplitController::class, 'show']);

    // Notifications (F8)
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);

    // Reports (F9)
    Route::get('reports/custom', [ReportController::class, 'custom']);
    Route::get('reports/tax', [ReportController::class, 'tax']);
    Route::get('reports/interest-certificate', [ReportController::class, 'interestCertificate']);

    // Service requests (F10)
    Route::get('service-requests', [ServiceRequestController::class, 'index']);
    Route::post('service-requests', [ServiceRequestController::class, 'store']);
    Route::get('service-requests/{id}', [ServiceRequestController::class, 'show']);

    // Support, branches, ATMs, appointments, FAQs (F11)
    Route::get('support-tickets', [SupportTicketController::class, 'index']);
    Route::post('support-tickets', [SupportTicketController::class, 'store']);
    Route::get('support-tickets/{id}', [SupportTicketController::class, 'show']);
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{id}', [BranchController::class, 'show']);
    Route::get('atms', [AtmController::class, 'index']);
    Route::get('atms/{id}', [AtmController::class, 'show']);
    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::get('appointments/{id}', [AppointmentController::class, 'show']);
    Route::delete('appointments/{id}', [AppointmentController::class, 'destroy']);
    Route::get('faqs', [FaqController::class, 'index']);

    // Security (F12)
    Route::get('security/login-history', [SecurityController::class, 'loginHistory']);
    Route::get('security/transaction-limits', [SecurityController::class, 'transactionLimits']);
    Route::put('security/transaction-limits', [SecurityController::class, 'updateTransactionLimits']);
    Route::post('security/fraud-report', [SecurityController::class, 'reportFraud']);
    Route::post('security/change-password', [SecurityController::class, 'changePassword']);
    Route::post('security/change-pin', [SecurityController::class, 'changePin']);
    Route::post('security/biometric', [SecurityController::class, 'biometric']);

    // Trade & Corporate (F13, F14)
    Route::get('letter-of-credit', [LetterOfCreditController::class, 'index']);
    Route::post('letter-of-credit', [LetterOfCreditController::class, 'store']);
    Route::get('letter-of-credit/{id}', [LetterOfCreditController::class, 'show']);
    Route::get('bank-guarantees', [BankGuaranteeController::class, 'index']);
    Route::post('bank-guarantees', [BankGuaranteeController::class, 'store']);
    Route::get('bank-guarantees/{id}', [BankGuaranteeController::class, 'show']);
    Route::get('forex-bookings', [ForexBookingController::class, 'index']);
    Route::post('forex-bookings', [ForexBookingController::class, 'store']);
    Route::get('forex-bookings/{id}', [ForexBookingController::class, 'show']);
    Route::get('corporate/accounts', [CorporateController::class, 'accounts']);
    Route::get('corporate/accounts/{corporateAccountId}/sub-users', [CorporateController::class, 'subUsers']);
    Route::post('corporate/accounts/{corporateAccountId}/sub-users', [CorporateController::class, 'addSubUser']);
    Route::get('corporate/accounts/{corporateAccountId}/approval-workflows', [CorporateController::class, 'approvalWorkflows']);
    Route::get('corporate/accounts/{corporateAccountId}/approval-rights', [CorporateController::class, 'userApprovalRights']);
    Route::post('corporate/approval-rights', [CorporateController::class, 'assignApprovalRight']);

    // WhatsApp preferences & clear data (compliance)
    Route::get('whatsapp-preferences', [\App\Http\Controllers\Api\V1\WhatsAppPreferencesController::class, 'show']);
    Route::put('whatsapp-preferences', [\App\Http\Controllers\Api\V1\WhatsAppPreferencesController::class, 'update']);
    Route::post('whatsapp-clear-data', [\App\Http\Controllers\Api\V1\WhatsAppPreferencesController::class, 'clearData']);
    Route::get('whatsapp-pending-tasks', [\App\Http\Controllers\Api\V1\WhatsAppPreferencesController::class, 'listPendingTasks']);
    Route::post('whatsapp-pending-tasks/cancel-all', [\App\Http\Controllers\Api\V1\WhatsAppPreferencesController::class, 'cancelAllPendingTasks']);
});
