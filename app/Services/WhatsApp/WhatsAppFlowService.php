<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * WhatsApp Flows: send flow messages and build flow JSON for registration.
 * Flows provide structured forms (lists, amount input, PIN/passcode) in chat.
 */
class WhatsAppFlowService
{
    protected WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Send an interactive flow message to the user (within 24h session).
     * Uses flow_id from config (flow must be published in Meta Business Suite / Graph API).
     */
    public function sendFlow(string $to, string $flowType, string $bodyText = '', string $buttonText = 'Open'): array
    {
        $flowId = Config::get("whatsapp.flows.flow_ids.{$flowType}");
        if (empty($flowId)) {
            Log::channel('whatsapp')->warning('WhatsApp Flow: no flow_id configured', ['flow_type' => $flowType]);
            return ['error' => 'Flow not configured'];
        }

        return $this->whatsappService->sendFlowMessage($to, $flowId, $bodyText ?: $this->defaultBody($flowType), $buttonText ?: 'Open');
    }

    protected function defaultBody(string $flowType): string
    {
        return match ($flowType) {
            'transfer' => 'Tap below to fill in the transfer form.',
            'loan_application' => 'Tap below to apply for a loan.',
            'card_block' => 'Tap below to select a card to block.',
            'amount_passcode' => 'Tap below to enter amount and confirm with your PIN.',
            'add_beneficiary' => 'Tap below to add a new beneficiary.',
            'recurring_transfer' => 'Tap below to set up a standing order.',
            'cardless_withdrawal' => 'Tap below to get an ATM withdrawal code.',
            'loan_repayment' => 'Tap below to make a loan repayment.',
            'fixed_deposit' => 'Tap below to open a fixed deposit.',
            'investment' => 'Tap below to invest in a product.',
            'card_freeze' => 'Tap below to freeze a card.',
            'card_unfreeze' => 'Tap below to unfreeze a card.',
            'book_appointment' => 'Tap below to book a branch appointment.',
            'support_ticket' => 'Tap below to create a support ticket.',
            default => 'Tap below to continue.',
        };
    }

    /**
     * Return flow JSON for a given flow type (for registration via Graph API or inline send).
     * Injects data_channel_uri from config.
     */
    public function getFlowJson(string $flowType): array
    {
        $dataChannelUri = rtrim(Config::get('app.url'), '/') . '/api/webhooks/whatsapp-flows/data';
        $definitions = new FlowDefinitions;

        return $definitions->get($flowType, $dataChannelUri);
    }
}
