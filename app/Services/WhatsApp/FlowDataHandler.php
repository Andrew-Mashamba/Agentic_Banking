<?php

namespace App\Services\WhatsApp;

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\Card;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Transfer;
use App\Models\LoanApplication;
use App\Services\BankingAuditService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles WhatsApp Flow data_exchange requests: return screen data (lists, etc.) and process completion.
 */
class FlowDataHandler
{
    public function __construct(
        protected PendingTaskService $pendingTaskService
    ) {
    }

    public function handle(string $flowType, ?string $screen, array $data, ?string $flowToken, User $user): array
    {
        $stateKey = $flowToken ? "wa_flow:{$flowToken}" : null;
        $state = $stateKey ? (Cache::get($stateKey) ?? []) : [];

        $response = match ($flowType) {
            'transfer' => $this->handleTransfer($screen, $data, $state, $user, $stateKey),
            'loan_application' => $this->handleLoanApplication($screen, $data, $state, $user, $stateKey),
            'card_block' => $this->handleCardBlock($screen, $data, $state, $user, $stateKey),
            'amount_passcode' => $this->handleAmountPasscode($screen, $data, $state, $user, $stateKey),
            'add_beneficiary' => $this->handleAddBeneficiary($screen, $data, $state, $user, $stateKey),
            'recurring_transfer' => $this->handleRecurringTransfer($screen, $data, $state, $user, $stateKey),
            'cardless_withdrawal' => $this->handleCardlessWithdrawal($screen, $data, $state, $user, $stateKey),
            'loan_repayment' => $this->handleLoanRepayment($screen, $data, $state, $user, $stateKey),
            'fixed_deposit' => $this->handleFixedDeposit($screen, $data, $state, $user, $stateKey),
            'investment' => $this->handleInvestment($screen, $data, $state, $user, $stateKey),
            'card_freeze' => $this->handleCardFreeze($screen, $data, $state, $user, $stateKey),
            'card_unfreeze' => $this->handleCardUnfreeze($screen, $data, $state, $user, $stateKey),
            'book_appointment' => $this->handleBookAppointment($screen, $data, $state, $user, $stateKey),
            'support_ticket' => $this->handleSupportTicket($screen, $data, $state, $user, $stateKey),
            default => $this->handleTransfer($screen, $data, $state, $user, $stateKey),
        };

        if ($stateKey && ! empty($response['state'])) {
            Cache::put($stateKey, $response['state'], 3600);
        }

        // When a flow completes successfully, clear any pending task of this type (resumable workflow done)
        if (($response['screen'] ?? '') === 'SUCCESS') {
            $this->pendingTaskService->completeByType($user, $flowType);
        }

        unset($response['state']);
        return $response;
    }

    protected function handleTransfer(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $accounts = $user->accounts()->where('status', 'active')->get();
            $options = $accounts->map(fn ($a) => [
                'id' => (string) $a->id,
                'title' => $a->type . ' ****' . substr($a->account_number, -4) . ' - ' . $a->currency . ' ' . number_format($a->balance, 2),
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'FROM_ACCOUNT',
                'data' => ['from_account' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'FROM_ACCOUNT') {
            $beneficiaries = $user->beneficiaries()->orderBy('name')->get();
            $options = $beneficiaries->map(fn ($b) => [
                'id' => (string) $b->id,
                'title' => $b->name . ' (' . $b->type . ')',
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'TO_BENEFICIARY',
                'data' => ['to_beneficiary' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'TO_BENEFICIARY') {
            return [
                'version' => '3.0',
                'screen' => 'AMOUNT',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'AMOUNT') {
            return [
                'version' => '3.0',
                'screen' => 'REFERENCE',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'REFERENCE') {
            return [
                'version' => '3.0',
                'screen' => 'CONFIRM',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'CONFIRM') {
            $confirm = $data['confirm'] ?? $state['confirm'] ?? '';
            if (strtoupper(trim($confirm)) !== 'YES') {
                return [
                    'version' => '3.0',
                    'screen' => 'CONFIRM',
                    'data' => ['error' => 'Please type YES to confirm.'],
                    'state' => $state,
                ];
            }

            $fromAccount = Account::where('user_id', $user->id)->find($state['from_account'] ?? 0);
            $beneficiary = Beneficiary::where('user_id', $user->id)->find($state['to_beneficiary'] ?? 0);
            $amount = (float) ($state['amount'] ?? 0);

            if (! $fromAccount || ! $beneficiary || $amount <= 0) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid transfer details. Please start again.'],
                    'state' => [],
                ];
            }

            $transfer = Transfer::create([
                'from_account_id' => $fromAccount->id,
                'beneficiary_id' => $beneficiary->id,
                'amount' => $amount,
                'currency' => $fromAccount->currency,
                'type' => 'same_bank',
                'reference' => $state['reference'] ?? null,
                'status' => 'pending',
            ]);

            BankingAuditService::log('transfer', 'transfer', $transfer->id, [
                'amount' => $transfer->amount,
                'currency' => $transfer->currency,
                'type' => $transfer->type,
                'status' => $transfer->status,
            ]);

            if ($stateKey) {
                Cache::forget($stateKey);
            }

            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Transfer of ' . number_format($amount, 2) . ' ' . $fromAccount->currency . ' to ' . $beneficiary->name . ' has been submitted. Ref: ' . $transfer->id,
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'FROM_ACCOUNT', 'data' => [], 'state' => $state];
    }

    protected function handleLoanApplication(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $products = LoanProduct::where('is_active', true)->get();
            $options = $products->map(fn ($p) => [
                'id' => (string) $p->id,
                'title' => $p->name . ' (' . $p->min_amount . '-' . $p->max_amount . ' ' . $p->currency . ')',
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'LOAN_PRODUCT',
                'data' => ['loan_product' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'LOAN_PRODUCT') {
            return [
                'version' => '3.0',
                'screen' => 'LOAN_AMOUNT',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'LOAN_AMOUNT') {
            return [
                'version' => '3.0',
                'screen' => 'LOAN_TENOR',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'LOAN_TENOR') {
            return [
                'version' => '3.0',
                'screen' => 'LOAN_PURPOSE',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'LOAN_PURPOSE') {
            $product = LoanProduct::find($state['loan_product'] ?? 0);
            if (! $product || ! $product->is_active) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid loan product.'],
                    'state' => [],
                ];
            }
            $amount = (float) ($state['amount_requested'] ?? 0);
            $tenor = (int) ($state['tenor_months'] ?? 0);
            if ($amount < $product->min_amount || $amount > $product->max_amount || $tenor < 1) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Amount or tenor out of range.'],
                    'state' => [],
                ];
            }

            $application = LoanApplication::create([
                'user_id' => $user->id,
                'loan_product_id' => $product->id,
                'amount_requested' => $amount,
                'tenor_months' => $tenor,
                'purpose' => $state['purpose'] ?? null,
                'status' => 'pending',
            ]);

            BankingAuditService::log('loan_application', 'loan_application', $application->id, [
                'amount_requested' => $application->amount_requested,
                'tenor_months' => $application->tenor_months,
                'status' => $application->status,
            ]);

            if ($stateKey) {
                Cache::forget($stateKey);
            }

            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Loan application for ' . number_format($amount, 2) . ' over ' . $tenor . ' months submitted. Ref: ' . $application->id,
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'LOAN_PRODUCT', 'data' => [], 'state' => $state];
    }

    protected function handleCardBlock(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $cards = Card::where('user_id', $user->id)->whereIn('status', ['active', 'frozen'])->get();
            $options = $cards->map(fn ($c) => [
                'id' => (string) $c->id,
                'title' => $c->type . ' ****' . $c->last_four . ' - ' . $c->status,
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'SELECT_CARD',
                'data' => ['card_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'SELECT_CARD') {
            return [
                'version' => '3.0',
                'screen' => 'CONFIRM_BLOCK',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'CONFIRM_BLOCK') {
            $confirm = $data['confirm'] ?? $state['confirm'] ?? '';
            if (strtoupper(trim($confirm)) !== 'BLOCK') {
                return [
                    'version' => '3.0',
                    'screen' => 'CONFIRM_BLOCK',
                    'data' => ['error' => 'Type BLOCK to confirm.'],
                    'state' => $state,
                ];
            }

            $card = Card::where('user_id', $user->id)->find($state['card_id'] ?? 0);
            if (! $card) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Card not found.'],
                    'state' => [],
                ];
            }

            $card->update(['status' => 'blocked']);
            BankingAuditService::log('card_block', 'card', $card->id, ['last_four' => $card->last_four]);

            if ($stateKey) {
                Cache::forget($stateKey);
            }

            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Card ****' . $card->last_four . ' has been blocked.',
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'SELECT_CARD', 'data' => [], 'state' => $state];
    }

    protected function handleAmountPasscode(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            return [
                'version' => '3.0',
                'screen' => 'AMOUNT',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'AMOUNT') {
            return [
                'version' => '3.0',
                'screen' => 'PASSCODE',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'PASSCODE') {
            // In production you would verify PIN against user's transaction_pin_hash
            $amount = $state['amount'] ?? 0;
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Amount ' . number_format((float) $amount, 2) . ' confirmed.',
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'AMOUNT', 'data' => [], 'state' => $state];
    }

    protected function handleAddBeneficiary(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $options = [
                ['id' => 'same_bank', 'title' => 'Same bank'],
                ['id' => 'interbank', 'title' => 'Other bank'],
                ['id' => 'mobile_wallet', 'title' => 'Mobile wallet'],
                ['id' => 'international', 'title' => 'International'],
            ];
            return [
                'version' => '3.0',
                'screen' => 'BENEFICIARY_TYPE',
                'data' => ['type' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'BENEFICIARY_TYPE') {
            return ['version' => '3.0', 'screen' => 'BENEFICIARY_NAME', 'data' => [], 'state' => $state];
        }
        if ($screen === 'BENEFICIARY_NAME') {
            return ['version' => '3.0', 'screen' => 'ACCOUNT_NUMBER', 'data' => [], 'state' => $state];
        }
        if ($screen === 'ACCOUNT_NUMBER') {
            return ['version' => '3.0', 'screen' => 'BANK_DETAILS', 'data' => [], 'state' => $state];
        }
        if ($screen === 'BANK_DETAILS') {
            return ['version' => '3.0', 'screen' => 'CONFIRM_BENEFICIARY', 'data' => [], 'state' => $state];
        }

        if ($screen === 'CONFIRM_BENEFICIARY') {
            if (strtoupper(trim($state['confirm'] ?? '')) !== 'YES') {
                return [
                    'version' => '3.0',
                    'screen' => 'CONFIRM_BENEFICIARY',
                    'data' => ['error' => 'Type YES to confirm.'],
                    'state' => $state,
                ];
            }
            $name = trim($state['name'] ?? '');
            $accountNumber = trim($state['account_number'] ?? '');
            if (! $name || ! $accountNumber) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Name and account number are required.'],
                    'state' => [],
                ];
            }
            $beneficiary = Beneficiary::create([
                'user_id' => $user->id,
                'name' => $name,
                'account_number' => $accountNumber,
                'bank_name' => trim($state['bank_name'] ?? '') ?: null,
                'type' => $state['type'] ?? 'interbank',
            ]);
            BankingAuditService::log('beneficiary_add', 'beneficiary', $beneficiary->id, ['name' => $beneficiary->name]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Beneficiary ' . $beneficiary->name . ' has been saved. You can now transfer to them.',
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'BENEFICIARY_TYPE', 'data' => [], 'state' => $state];
    }

    protected function handleRecurringTransfer(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $accounts = $user->accounts()->where('status', 'active')->get();
            $options = $accounts->map(fn ($a) => [
                'id' => (string) $a->id,
                'title' => $a->type . ' ****' . substr($a->account_number, -4),
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'FROM_ACCOUNT',
                'data' => ['from_account_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'FROM_ACCOUNT') {
            $beneficiaries = $user->beneficiaries()->orderBy('name')->get();
            $options = $beneficiaries->map(fn ($b) => [
                'id' => (string) $b->id,
                'title' => $b->name . ' (' . $b->type . ')',
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'TO_BENEFICIARY',
                'data' => ['beneficiary_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'TO_BENEFICIARY') {
            return ['version' => '3.0', 'screen' => 'AMOUNT', 'data' => [], 'state' => $state];
        }
        if ($screen === 'AMOUNT') {
            $options = [
                ['id' => 'daily', 'title' => 'Daily'],
                ['id' => 'weekly', 'title' => 'Weekly'],
                ['id' => 'monthly', 'title' => 'Monthly'],
            ];
            return [
                'version' => '3.0',
                'screen' => 'FREQUENCY',
                'data' => ['frequency' => $options],
                'state' => $state,
            ];
        }
        if ($screen === 'FREQUENCY') {
            return [
                'version' => '3.0',
                'screen' => 'CONFIRM_RECURRING',
                'data' => [],
                'state' => $state,
            ];
        }

        if ($screen === 'CONFIRM_RECURRING') {
            if (strtoupper(trim($state['confirm'] ?? '')) !== 'YES') {
                return [
                    'version' => '3.0',
                    'screen' => 'CONFIRM_RECURRING',
                    'data' => ['error' => 'Type YES to create standing order.'],
                    'state' => $state,
                ];
            }
            $fromAccountId = (int) ($state['from_account_id'] ?? 0);
            $beneficiaryId = (int) ($state['beneficiary_id'] ?? 0);
            $amount = (float) ($state['amount'] ?? 0);
            $frequency = $state['frequency'] ?? 'monthly';
            if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
                $frequency = 'monthly';
            }
            $account = Account::where('user_id', $user->id)->find($fromAccountId);
            $beneficiary = Beneficiary::where('user_id', $user->id)->find($beneficiaryId);
            if (! $account || ! $beneficiary || $amount <= 0) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid details. Please start again.'],
                    'state' => [],
                ];
            }
            $nextRun = match ($frequency) {
                'daily' => now()->addDay(),
                'weekly' => now()->addWeek(),
                default => now()->addMonth(),
            };
            $id = DB::table('recurring_transfers')->insertGetId([
                'user_id' => $user->id,
                'from_account_id' => $account->id,
                'beneficiary_id' => $beneficiary->id,
                'amount' => $amount,
                'frequency' => $frequency,
                'next_run_at' => $nextRun,
                'end_at' => null,
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]);
            BankingAuditService::log('recurring_transfer', 'recurring_transfer', (int) $id, ['amount' => $amount, 'frequency' => $frequency]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Standing order created. USD ' . number_format($amount, 2) . ' ' . $frequency . ' to ' . $beneficiary->name . '. Ref: ' . $id,
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'FROM_ACCOUNT', 'data' => [], 'state' => $state];
    }

    protected function handleCardlessWithdrawal(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $accounts = $user->accounts()->where('status', 'active')->get();
            $options = $accounts->map(fn ($a) => [
                'id' => (string) $a->id,
                'title' => $a->type . ' ****' . substr($a->account_number, -4) . ' - ' . $a->currency . ' ' . number_format($a->balance, 2),
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'ACCOUNT',
                'data' => ['account_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'ACCOUNT') {
            return ['version' => '3.0', 'screen' => 'AMOUNT', 'data' => [], 'state' => $state];
        }

        if ($screen === 'AMOUNT') {
            $accountId = (int) ($state['account_id'] ?? 0);
            $amount = (float) ($state['amount'] ?? 0);
            $account = Account::where('user_id', $user->id)->find($accountId);
            if (! $account || $amount < 1) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid account or amount.'],
                    'state' => [],
                ];
            }
            if ($account->balance < $amount) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Insufficient balance. Available: ' . $account->currency . ' ' . number_format($account->balance, 2)],
                    'state' => [],
                ];
            }
            $code = str_pad((string) random_int(100000, 999999), 6, '0');
            $expiresAt = now()->addHours(2);
            $id = DB::table('cardless_withdrawals')->insertGetId([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'code' => $code,
                'expires_at' => $expiresAt,
                'status' => 'pending',
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]);
            BankingAuditService::log('cardless_withdrawal', 'cardless_withdrawal', (int) $id, ['amount' => $amount]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Your withdrawal code is ' . $code . '. Valid for 2 hours. Amount: USD ' . number_format($amount, 2) . '. Use at any of our ATMs.',
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'ACCOUNT', 'data' => [], 'state' => $state];
    }

    protected function handleLoanRepayment(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $loans = Loan::where('user_id', $user->id)->whereIn('status', ['active', 'defaulted'])->get();
            $options = $loans->map(fn ($l) => [
                'id' => (string) $l->id,
                'title' => 'Loan #' . $l->id . ' - ' . number_format($l->outstanding_balance ?? $l->principal_amount, 2),
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'LOAN',
                'data' => ['loan_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'LOAN') {
            return ['version' => '3.0', 'screen' => 'AMOUNT', 'data' => [], 'state' => $state];
        }
        if ($screen === 'AMOUNT') {
            $accounts = $user->accounts()->where('status', 'active')->get();
            $options = $accounts->map(fn ($a) => [
                'id' => (string) $a->id,
                'title' => $a->type . ' ****' . substr($a->account_number, -4),
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'FROM_ACCOUNT',
                'data' => ['account_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'FROM_ACCOUNT') {
            $loanId = (int) ($state['loan_id'] ?? 0);
            $amount = (float) ($state['amount'] ?? 0);
            $accountId = (int) ($state['account_id'] ?? 0);
            $loan = Loan::where('user_id', $user->id)->find($loanId);
            $account = Account::where('user_id', $user->id)->find($accountId);
            if (! $loan || ! $account || $amount <= 0) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid loan or account.'],
                    'state' => [],
                ];
            }
            $id = DB::table('loan_repayments')->insertGetId([
                'loan_id' => $loan->id,
                'amount' => $amount,
                'paid_at' => now(),
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Repayment of USD ' . number_format($amount, 2) . ' recorded for loan #' . $loan->id . '. Ref: ' . $id,
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'LOAN', 'data' => [], 'state' => $state];
    }

    protected function handleFixedDeposit(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $accounts = $user->accounts()->where('status', 'active')->get();
            $options = $accounts->map(fn ($a) => [
                'id' => (string) $a->id,
                'title' => $a->type . ' ****' . substr($a->account_number, -4) . ' - ' . $a->currency . ' ' . number_format($a->balance, 2),
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'ACCOUNT',
                'data' => ['account_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'ACCOUNT') {
            return ['version' => '3.0', 'screen' => 'AMOUNT', 'data' => [], 'state' => $state];
        }
        if ($screen === 'AMOUNT') {
            return ['version' => '3.0', 'screen' => 'TENOR', 'data' => [], 'state' => $state];
        }

        if ($screen === 'TENOR') {
            $accountId = (int) ($state['account_id'] ?? 0);
            $amount = (float) ($state['amount'] ?? 0);
            $tenorMonths = (int) ($state['tenor_months'] ?? 0);
            $account = Account::where('user_id', $user->id)->find($accountId);
            if (! $account || $amount < 1 || $tenorMonths < 1 || $tenorMonths > 120) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid amount or term (1–120 months).'],
                    'state' => [],
                ];
            }
            if ($account->balance < $amount) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Insufficient balance.'],
                    'state' => [],
                ];
            }
            $maturityDate = now()->addMonths($tenorMonths);
            $id = DB::table('fixed_deposits')->insertGetId([
                'account_id' => $account->id,
                'amount' => $amount,
                'interest_rate' => 8.5,
                'tenor_months' => $tenorMonths,
                'maturity_date' => $maturityDate,
                'status' => 'active',
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]);
            BankingAuditService::log('fixed_deposit', 'fixed_deposit', (int) $id, ['amount' => $amount, 'tenor_months' => $tenorMonths]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Fixed deposit opened. USD ' . number_format($amount, 2) . ' for ' . $tenorMonths . ' months at 8.5% p.a. Maturity: ' . $maturityDate->format('Y-m-d') . '. Ref: ' . $id,
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'ACCOUNT', 'data' => [], 'state' => $state];
    }

    protected function handleInvestment(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $products = DB::table('investment_products')->where('is_active', true)->get();
            $options = collect($products)->map(fn ($p) => [
                'id' => (string) $p->id,
                'title' => ($p->name ?? 'Product ' . $p->id) . ' (' . ($p->type ?? '') . ')',
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'PRODUCT',
                'data' => ['investment_product_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'PRODUCT') {
            return ['version' => '3.0', 'screen' => 'AMOUNT', 'data' => [], 'state' => $state];
        }

        if ($screen === 'AMOUNT') {
            $productId = (int) ($state['investment_product_id'] ?? 0);
            $amount = (float) ($state['amount'] ?? 0);
            $product = DB::table('investment_products')->where('is_active', true)->find($productId);
            if (! $product || $amount < 1) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid product or amount.'],
                    'state' => [],
                ];
            }
            $id = DB::table('investments')->insertGetId([
                'user_id' => $user->id,
                'investment_product_id' => $productId,
                'amount' => $amount,
                'status' => 'active',
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]);
            BankingAuditService::log('investment', 'investment', (int) $id, ['amount' => $amount]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Investment of USD ' . number_format($amount, 2) . ' in ' . ($product->name ?? 'product') . ' completed. Ref: ' . $id,
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'PRODUCT', 'data' => [], 'state' => $state];
    }

    protected function handleCardFreeze(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $cards = Card::where('user_id', $user->id)->where('status', 'active')->get();
            $options = $cards->map(fn ($c) => [
                'id' => (string) $c->id,
                'title' => $c->type . ' ****' . $c->last_four,
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'SELECT_CARD',
                'data' => ['card_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'SELECT_CARD') {
            $card = Card::where('user_id', $user->id)->find((int) ($state['card_id'] ?? 0));
            if (! $card) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Card not found.'],
                    'state' => [],
                ];
            }
            $card->update(['status' => 'frozen']);
            BankingAuditService::log('card_freeze', 'card', $card->id, ['last_four' => $card->last_four]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Card ****' . $card->last_four . ' has been frozen. You can unfreeze it anytime.',
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'SELECT_CARD', 'data' => [], 'state' => $state];
    }

    protected function handleCardUnfreeze(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $cards = Card::where('user_id', $user->id)->where('status', 'frozen')->get();
            $options = $cards->map(fn ($c) => [
                'id' => (string) $c->id,
                'title' => $c->type . ' ****' . $c->last_four,
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'SELECT_CARD',
                'data' => ['card_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'SELECT_CARD') {
            $card = Card::where('user_id', $user->id)->find((int) ($state['card_id'] ?? 0));
            if (! $card) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Card not found.'],
                    'state' => [],
                ];
            }
            $card->update(['status' => 'active']);
            BankingAuditService::log('card_unfreeze', 'card', $card->id, ['last_four' => $card->last_four]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Card ****' . $card->last_four . ' has been reactivated.',
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'SELECT_CARD', 'data' => [], 'state' => $state];
    }

    protected function handleBookAppointment(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            $branches = DB::table('branches')->orderBy('name')->get();
            $options = $branches->map(fn ($b) => [
                'id' => (string) $b->id,
                'title' => ($b->name ?? 'Branch ' . $b->id) . ' - ' . ($b->address ?? ''),
            ])->values()->toArray();
            return [
                'version' => '3.0',
                'screen' => 'BRANCH',
                'data' => ['branch_id' => $options],
                'state' => $state,
            ];
        }

        if ($screen === 'BRANCH') {
            return ['version' => '3.0', 'screen' => 'DATE_TIME', 'data' => [], 'state' => $state];
        }
        if ($screen === 'DATE_TIME') {
            return ['version' => '3.0', 'screen' => 'CONFIRM_APPOINTMENT', 'data' => [], 'state' => $state];
        }

        if ($screen === 'CONFIRM_APPOINTMENT') {
            if (strtoupper(trim($state['confirm'] ?? '')) !== 'YES') {
                return [
                    'version' => '3.0',
                    'screen' => 'CONFIRM_APPOINTMENT',
                    'data' => ['error' => 'Type YES to confirm.'],
                    'state' => $state,
                ];
            }
            $branchId = (int) ($state['branch_id'] ?? 0);
            $scheduledAt = trim($state['scheduled_at'] ?? '');
            $branch = DB::table('branches')->find($branchId);
            if (! $branch) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Branch not found.'],
                    'state' => [],
                ];
            }
            try {
                $dt = new \DateTime($scheduledAt);
                if ($dt <= new \DateTime) {
                    return [
                        'version' => '3.0',
                        'screen' => 'ERROR',
                        'data' => ['error' => 'Please choose a future date and time.'],
                        'state' => [],
                    ];
                }
            } catch (\Throwable) {
                return [
                    'version' => '3.0',
                    'screen' => 'ERROR',
                    'data' => ['error' => 'Invalid date/time. Use format: 2026-03-01 14:00'],
                    'state' => [],
                ];
            }
            $id = DB::table('appointments')->insertGetId([
                'user_id' => $user->id,
                'type' => 'branch',
                'branch_id' => $branchId,
                'scheduled_at' => $scheduledAt,
                'notes' => null,
                'status' => 'scheduled',
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Appointment booked at ' . ($branch->name ?? 'branch') . ' on ' . $scheduledAt . '. Ref: ' . $id,
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'BRANCH', 'data' => [], 'state' => $state];
    }

    protected function handleSupportTicket(?string $screen, array $data, array $state, User $user, ?string $stateKey): array
    {
        $state = array_merge($state, $data);

        if ($screen === null || $screen === '' || $screen === 'INIT') {
            return ['version' => '3.0', 'screen' => 'SUBJECT', 'data' => [], 'state' => $state];
        }

        if ($screen === 'SUBJECT') {
            $subject = trim($state['subject'] ?? '');
            if (! $subject) {
                return [
                    'version' => '3.0',
                    'screen' => 'SUBJECT',
                    'data' => ['error' => 'Please enter a subject.'],
                    'state' => $state,
                ];
            }
            $ticketNumber = 'TKT-' . strtoupper(bin2hex(random_bytes(3)));
            $id = DB::table('support_tickets')->insertGetId([
                'user_id' => $user->id,
                'subject' => $subject,
                'status' => 'open',
                'ticket_number' => $ticketNumber,
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]);
            if ($stateKey) {
                Cache::forget($stateKey);
            }
            return [
                'version' => '3.0',
                'screen' => 'SUCCESS',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $stateKey ? str_replace('wa_flow:', '', $stateKey) : null,
                            'message' => 'Support ticket created. Reference: ' . $ticketNumber . '. Our team will respond shortly.',
                        ],
                    ],
                ],
            ];
        }

        return ['version' => '3.0', 'screen' => 'SUBJECT', 'data' => [], 'state' => $state];
    }
}
