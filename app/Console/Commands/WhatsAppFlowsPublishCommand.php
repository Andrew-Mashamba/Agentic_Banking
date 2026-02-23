<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\FlowDefinitions;
use Illuminate\Console\Command;

/**
 * Output Flow JSON for each flow type so you can register/publish in Meta Business Suite or via Graph API.
 * Usage: php artisan whatsapp:flows-publish [type]
 * Types: transfer, loan_application, card_block, amount_passcode, add_beneficiary, recurring_transfer, cardless_withdrawal, loan_repayment, fixed_deposit, investment, card_freeze, card_unfreeze, book_appointment, support_ticket
 */
class WhatsAppFlowsPublishCommand extends Command
{
    protected $signature = 'whatsapp:flows-publish {type? : flow type (e.g. transfer, add_beneficiary)}';

    protected $description = 'Output WhatsApp Flow JSON for registration with Meta (paste in Flow playground or use Flows API)';

    public function handle(): int
    {
        $dataChannelUri = rtrim(config('app.url'), '/') . '/api/webhooks/whatsapp-flows/data';
        $definitions = new FlowDefinitions;

        $allTypes = array_keys(config('whatsapp.flows.flow_ids', []));
        $types = $this->argument('type')
            ? [$this->argument('type')]
            : $allTypes;

        foreach ($types as $type) {
            $json = $definitions->get($type, $dataChannelUri);
            $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->line("=== Flow: {$type} ===");
            $this->line($encoded);
            $this->newLine();
        }

        $this->info('Register each flow via Meta Business Suite (WhatsApp > Flows) or POST to Graph API /{WABA_ID}/flows. Then set WHATSAPP_FLOW_ID_TRANSFER etc. in .env');

        return self::SUCCESS;
    }
}
