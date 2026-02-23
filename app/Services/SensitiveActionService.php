<?php

namespace App\Services;

use App\Models\Card;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\PendingSensitiveAction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * OTP-gated execution of sensitive banking actions (transfer, card block, loan application).
 * AI requests an action -> we create pending, send OTP via WhatsApp -> user replies with code -> AI calls confirm.
 */
class SensitiveActionService
{
    public const OTP_EXIRY_MINUTES = 10;

    public const ACTION_TRANSFER = 'transfer';
    public const ACTION_CARD_BLOCK = 'card_block';
    public const ACTION_LOAN_APPLICATION = 'loan_application';

    public function __construct(
        protected WhatsAppService $whatsappService
    ) {
    }

    /**
     * Request a sensitive action: create pending record, send OTP to user's phone, return token.
     */
    public function requestAction(int $userId, string $actionType, array $payload): array
    {
        $user = User::findOrFail($userId);
        $otp = str_pad((string) random_int(100000, 999999), 6, '0');
        $token = Str::random(48);
        $expiresAt = now()->addMinutes(self::OTP_EXIRY_MINUTES);

        PendingSensitiveAction::create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'payload' => $payload,
            'otp_hash' => Hash::make($otp),
            'token' => $token,
            'expires_at' => $expiresAt,
            'used' => false,
        ]);

        $this->whatsappService->sendTextMessage(
            $user->phone_number,
            "Your verification code is: {$otp}. It expires in " . self::OTP_EXIRY_MINUTES . " minutes. Do not share this code."
        );

        return [
            'pending_token' => $token,
            'expires_in_minutes' => self::OTP_EXIRY_MINUTES,
        ];
    }

    /**
     * Confirm with OTP and execute the pending action.
     */
    public function confirmAction(string $token, string $code): array
    {
        $pending = PendingSensitiveAction::where('token', $token)->first();
        if (! $pending) {
            throw ValidationException::withMessages(['token' => ['Invalid or expired token.']]);
        }
        if ($pending->used) {
            throw ValidationException::withMessages(['token' => ['This confirmation has already been used.']]);
        }
        if ($pending->isExpired()) {
            throw ValidationException::withMessages(['token' => ['Token has expired. Please request a new code.']]);
        }
        if (! Hash::check($code, $pending->otp_hash)) {
            throw ValidationException::withMessages(['code' => ['Invalid verification code.']]);
        }

        $pending->update(['used' => true]);
        $user = $pending->user;

        return match ($pending->action_type) {
            self::ACTION_TRANSFER => $this->executeTransfer($user, $pending->payload),
            self::ACTION_CARD_BLOCK => $this->executeCardBlock($user, $pending->payload),
            self::ACTION_LOAN_APPLICATION => $this->executeLoanApplication($user, $pending->payload),
            default => throw ValidationException::withMessages(['action_type' => ['Unknown action type.']]),
        };
    }

    protected function executeTransfer(User $user, array $payload): array
    {
        $from = \App\Models\Account::where('user_id', $user->id)->find($payload['from_account_id'] ?? 0);
        if (! $from) {
            throw ValidationException::withMessages(['payload' => ['From account not found.']]);
        }
        $transfer = Transfer::create([
            'from_account_id' => $payload['from_account_id'],
            'to_account_id' => $payload['to_account_id'] ?? null,
            'beneficiary_id' => $payload['beneficiary_id'] ?? null,
            'amount' => $payload['amount'],
            'currency' => $payload['currency'] ?? $from->currency,
            'type' => $payload['type'] ?? 'same_bank',
            'reference' => $payload['reference'] ?? null,
            'status' => $payload['scheduled_at'] ?? null ? 'scheduled' : 'pending',
            'scheduled_at' => $payload['scheduled_at'] ?? null,
        ]);
        BankingAuditService::log('transfer', 'transfer', $transfer->id, [
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'type' => $transfer->type,
            'status' => $transfer->status,
        ]);
        return ['success' => true, 'resource' => 'transfer', 'id' => $transfer->id];
    }

    protected function executeCardBlock(User $user, array $payload): array
    {
        $card = Card::where('user_id', $user->id)->find($payload['card_id'] ?? 0);
        if (! $card) {
            throw ValidationException::withMessages(['payload' => ['Card not found.']]);
        }
        $card->update(['status' => 'blocked']);
        BankingAuditService::log('card_block', 'card', $card->id, ['last_four' => $card->last_four]);
        return ['success' => true, 'resource' => 'card', 'id' => $card->id];
    }

    protected function executeLoanApplication(User $user, array $payload): array
    {
        $product = LoanProduct::find($payload['loan_product_id'] ?? 0);
        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages(['payload' => ['Loan product not found or inactive.']]);
        }
        $application = LoanApplication::create([
            'user_id' => $user->id,
            'loan_product_id' => $payload['loan_product_id'],
            'amount_requested' => $payload['amount_requested'],
            'tenor_months' => $payload['tenor_months'],
            'purpose' => $payload['purpose'] ?? null,
            'status' => 'pending',
        ]);
        BankingAuditService::log('loan_application', 'loan_application', $application->id, [
            'amount_requested' => $application->amount_requested,
            'tenor_months' => $application->tenor_months,
            'status' => $application->status,
        ]);
        return ['success' => true, 'resource' => 'loan_application', 'id' => $application->id];
    }
}
