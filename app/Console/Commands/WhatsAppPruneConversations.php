<?php

namespace App\Console\Commands;

use App\Models\GuestConversation;
use App\Services\WhatsApp\WhatsAppComplianceService;
use Illuminate\Console\Command;

class WhatsAppPruneConversations extends Command
{
    protected $signature = 'whatsapp:prune-conversations';

    protected $description = 'Delete guest_conversations older than configured retention (compliance).';

    public function handle(WhatsAppComplianceService $compliance): int
    {
        $days = $compliance->retentionDaysConversations();
        $cutoff = now()->subDays($days);
        $deleted = GuestConversation::where('created_at', '<', $cutoff)->delete();
        $this->info("Pruned {$deleted} conversation rows older than {$days} days.");
        return 0;
    }
}
