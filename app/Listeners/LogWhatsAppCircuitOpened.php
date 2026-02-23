<?php

namespace App\Listeners;

use App\Events\WhatsAppCircuitOpened;
use App\Services\WhatsApp\WhatsAppMetricsService;
use Illuminate\Support\Facades\Log;

class LogWhatsAppCircuitOpened
{
    public function handle(WhatsAppCircuitOpened $event): void
    {
        Log::channel('whatsapp')->warning('ALERT: WhatsApp AI circuit breaker opened', [
            'reason' => $event->reason,
        ]);
        app(WhatsAppMetricsService::class)->incrementCircuitOpens();
    }
}
