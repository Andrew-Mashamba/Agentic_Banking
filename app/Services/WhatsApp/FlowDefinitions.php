<?php

namespace App\Services\WhatsApp;

/**
 * WhatsApp Flow JSON definitions (Meta Flow JSON 5.0).
 * Used for flow registration and for inline flow_json when sending.
 * data_channel_uri is injected for dynamic data (lists, routing).
 */
class FlowDefinitions
{
    public function get(string $flowType, string $dataChannelUri): array
    {
        return match ($flowType) {
            'transfer' => $this->transferFlow($dataChannelUri),
            'loan_application' => $this->loanApplicationFlow($dataChannelUri),
            'card_block' => $this->cardBlockFlow($dataChannelUri),
            'amount_passcode' => $this->amountPasscodeFlow($dataChannelUri),
            'add_beneficiary' => $this->addBeneficiaryFlow($dataChannelUri),
            'recurring_transfer' => $this->recurringTransferFlow($dataChannelUri),
            'cardless_withdrawal' => $this->cardlessWithdrawalFlow($dataChannelUri),
            'loan_repayment' => $this->loanRepaymentFlow($dataChannelUri),
            'fixed_deposit' => $this->fixedDepositFlow($dataChannelUri),
            'investment' => $this->investmentFlow($dataChannelUri),
            'card_freeze' => $this->cardFreezeFlow($dataChannelUri),
            'card_unfreeze' => $this->cardUnfreezeFlow($dataChannelUri),
            'book_appointment' => $this->bookAppointmentFlow($dataChannelUri),
            'support_ticket' => $this->supportTicketFlow($dataChannelUri),
            default => $this->transferFlow($dataChannelUri),
        };
    }

    protected function transferFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('FROM_ACCOUNT', 'Select account', [
                    $this->dropdown('from_account', 'From account', true),
                    $this->footer('Continue', 'navigate', 'TO_BENEFICIARY'),
                ]),
                $this->screen('TO_BENEFICIARY', 'Select beneficiary', [
                    $this->dropdown('to_beneficiary', 'To beneficiary', true),
                    $this->footer('Continue', 'navigate', 'AMOUNT'),
                ]),
                $this->screen('AMOUNT', 'Enter amount', [
                    $this->textInput('amount', 'Amount (e.g. 100.00)', 'number', true),
                    $this->footer('Continue', 'navigate', 'REFERENCE'),
                ]),
                $this->screen('REFERENCE', 'Reference (optional)', [
                    $this->textInput('reference', 'Reference or note', 'text', false),
                    $this->footer('Continue', 'navigate', 'CONFIRM'),
                ]),
                $this->screen('CONFIRM', 'Confirm transfer', [
                    $this->textInput('confirm', 'Type YES to confirm', 'text', true),
                    $this->footer('Submit', 'complete', null),
                ]),
            ],
        ];
    }

    protected function loanApplicationFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('LOAN_PRODUCT', 'Select loan type', [
                    $this->dropdown('loan_product', 'Loan product', true),
                    $this->footer('Continue', 'navigate', 'LOAN_AMOUNT'),
                ]),
                $this->screen('LOAN_AMOUNT', 'Amount requested', [
                    $this->textInput('amount_requested', 'Amount (USD)', 'number', true),
                    $this->footer('Continue', 'navigate', 'LOAN_TENOR'),
                ]),
                $this->screen('LOAN_TENOR', 'Repayment period', [
                    $this->textInput('tenor_months', 'Tenor (months)', 'number', true),
                    $this->footer('Continue', 'navigate', 'LOAN_PURPOSE'),
                ]),
                $this->screen('LOAN_PURPOSE', 'Purpose', [
                    $this->textInput('purpose', 'Purpose of loan', 'text', false),
                    $this->footer('Submit', 'complete', null),
                ]),
            ],
        ];
    }

    protected function cardBlockFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('SELECT_CARD', 'Select card to block', [
                    $this->dropdown('card_id', 'Card', true),
                    $this->footer('Continue', 'navigate', 'CONFIRM_BLOCK'),
                ]),
                $this->screen('CONFIRM_BLOCK', 'Confirm block', [
                    $this->textInput('confirm', 'Type BLOCK to confirm', 'text', true),
                    $this->footer('Block card', 'complete', null),
                ]),
            ],
        ];
    }

    protected function amountPasscodeFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('AMOUNT', 'Enter amount', [
                    $this->textInput('amount', 'Amount', 'number', true),
                    $this->footer('Continue', 'navigate', 'PASSCODE'),
                ]),
                $this->screen('PASSCODE', 'Enter PIN', [
                    $this->textInput('passcode', '6-digit PIN', 'passcode', true),
                    $this->footer('Confirm', 'complete', null),
                ]),
            ],
        ];
    }

    protected function addBeneficiaryFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('BENEFICIARY_TYPE', 'Type of beneficiary', [
                    $this->dropdown('type', 'Type', true),
                    $this->footer('Continue', 'navigate', 'BENEFICIARY_NAME'),
                ]),
                $this->screen('BENEFICIARY_NAME', 'Beneficiary name', [
                    $this->textInput('name', 'Full name', 'text', true),
                    $this->footer('Continue', 'navigate', 'ACCOUNT_NUMBER'),
                ]),
                $this->screen('ACCOUNT_NUMBER', 'Account number', [
                    $this->textInput('account_number', 'Account number', 'text', true),
                    $this->footer('Continue', 'navigate', 'BANK_DETAILS'),
                ]),
                $this->screen('BANK_DETAILS', 'Bank name (optional)', [
                    $this->textInput('bank_name', 'Bank name', 'text', false),
                    $this->footer('Continue', 'navigate', 'CONFIRM_BENEFICIARY'),
                ]),
                $this->screen('CONFIRM_BENEFICIARY', 'Confirm', [
                    $this->textInput('confirm', 'Type YES to add', 'text', true),
                    $this->footer('Add', 'complete', null),
                ]),
            ],
        ];
    }

    protected function recurringTransferFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('FROM_ACCOUNT', 'From account', [
                    $this->dropdown('from_account_id', 'Account', true),
                    $this->footer('Continue', 'navigate', 'TO_BENEFICIARY'),
                ]),
                $this->screen('TO_BENEFICIARY', 'To beneficiary', [
                    $this->dropdown('beneficiary_id', 'Beneficiary', true),
                    $this->footer('Continue', 'navigate', 'AMOUNT'),
                ]),
                $this->screen('AMOUNT', 'Amount per transfer', [
                    $this->textInput('amount', 'Amount', 'number', true),
                    $this->footer('Continue', 'navigate', 'FREQUENCY'),
                ]),
                $this->screen('FREQUENCY', 'Frequency', [
                    $this->dropdown('frequency', 'How often', true),
                    $this->footer('Continue', 'navigate', 'CONFIRM_RECURRING'),
                ]),
                $this->screen('CONFIRM_RECURRING', 'Confirm standing order', [
                    $this->textInput('confirm', 'Type YES to create', 'text', true),
                    $this->footer('Create', 'complete', null),
                ]),
            ],
        ];
    }

    protected function cardlessWithdrawalFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('ACCOUNT', 'Select account', [
                    $this->dropdown('account_id', 'Account', true),
                    $this->footer('Continue', 'navigate', 'AMOUNT'),
                ]),
                $this->screen('AMOUNT', 'Withdrawal amount', [
                    $this->textInput('amount', 'Amount', 'number', true),
                    $this->footer('Generate code', 'complete', null),
                ]),
            ],
        ];
    }

    protected function loanRepaymentFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('LOAN', 'Select loan', [
                    $this->dropdown('loan_id', 'Loan', true),
                    $this->footer('Continue', 'navigate', 'AMOUNT'),
                ]),
                $this->screen('AMOUNT', 'Repayment amount', [
                    $this->textInput('amount', 'Amount', 'number', true),
                    $this->footer('Continue', 'navigate', 'FROM_ACCOUNT'),
                ]),
                $this->screen('FROM_ACCOUNT', 'Pay from account', [
                    $this->dropdown('account_id', 'Account', true),
                    $this->footer('Submit', 'complete', null),
                ]),
            ],
        ];
    }

    protected function fixedDepositFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('ACCOUNT', 'Source account', [
                    $this->dropdown('account_id', 'Account', true),
                    $this->footer('Continue', 'navigate', 'AMOUNT'),
                ]),
                $this->screen('AMOUNT', 'Deposit amount', [
                    $this->textInput('amount', 'Amount', 'number', true),
                    $this->footer('Continue', 'navigate', 'TENOR'),
                ]),
                $this->screen('TENOR', 'Term (months)', [
                    $this->textInput('tenor_months', 'Months (e.g. 12)', 'number', true),
                    $this->footer('Open FD', 'complete', null),
                ]),
            ],
        ];
    }

    protected function investmentFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('PRODUCT', 'Select product', [
                    $this->dropdown('investment_product_id', 'Product', true),
                    $this->footer('Continue', 'navigate', 'AMOUNT'),
                ]),
                $this->screen('AMOUNT', 'Investment amount', [
                    $this->textInput('amount', 'Amount', 'number', true),
                    $this->footer('Invest', 'complete', null),
                ]),
            ],
        ];
    }

    protected function cardFreezeFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('SELECT_CARD', 'Select card to freeze', [
                    $this->dropdown('card_id', 'Card', true),
                    $this->footer('Freeze', 'complete', null),
                ]),
            ],
        ];
    }

    protected function cardUnfreezeFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('SELECT_CARD', 'Select card to unfreeze', [
                    $this->dropdown('card_id', 'Card', true),
                    $this->footer('Unfreeze', 'complete', null),
                ]),
            ],
        ];
    }

    protected function bookAppointmentFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('BRANCH', 'Select branch', [
                    $this->dropdown('branch_id', 'Branch', true),
                    $this->footer('Continue', 'navigate', 'DATE_TIME'),
                ]),
                $this->screen('DATE_TIME', 'Preferred date and time', [
                    $this->textInput('scheduled_at', 'e.g. 2026-03-01 14:00', 'text', true),
                    $this->footer('Continue', 'navigate', 'CONFIRM_APPOINTMENT'),
                ]),
                $this->screen('CONFIRM_APPOINTMENT', 'Confirm', [
                    $this->textInput('confirm', 'Type YES to book', 'text', true),
                    $this->footer('Book', 'complete', null),
                ]),
            ],
        ];
    }

    protected function supportTicketFlow(string $dataChannelUri): array
    {
        return [
            'version' => '5.0',
            'data_api_version' => '3.0',
            'data_channel_uri' => $dataChannelUri,
            'screens' => [
                $this->screen('SUBJECT', 'What do you need help with?', [
                    $this->textInput('subject', 'Subject', 'text', true),
                    $this->footer('Submit', 'complete', null),
                ]),
            ],
        ];
    }

    protected function screen(string $id, string $title, array $formChildren): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    [
                        'type' => 'Form',
                        'name' => 'form_' . $id,
                        'children' => $formChildren,
                    ],
                ],
            ],
        ];
    }

    protected function dropdown(string $name, string $label, bool $required): array
    {
        return [
            'type' => 'Dropdown',
            'name' => $name,
            'label' => $label,
            'required' => $required,
            'data-source' => [], // Filled by data endpoint
        ];
    }

    protected function textInput(string $name, string $label, string $inputType, bool $required): array
    {
        $component = [
            'type' => 'TextInput',
            'name' => $name,
            'label' => $label,
            'required' => $required,
        ];
        if ($inputType !== 'text') {
            $component['input-type'] = $inputType; // number, text, password, passcode, email, phone, date
        }
        return $component;
    }

    protected function footer(string $label, string $action, ?string $nextScreen): array
    {
        $onClick = [
            'name' => $action === 'complete' ? 'complete' : 'navigate',
            'payload' => $action === 'complete' ? [] : ['next_screen' => $nextScreen],
        ];
        return [
            'type' => 'Footer',
            'label' => $label,
            'on-click-action' => $onClick,
        ];
    }
}
