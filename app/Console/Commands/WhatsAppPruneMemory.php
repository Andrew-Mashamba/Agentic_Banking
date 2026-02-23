<?php

namespace App\Console\Commands;

use App\Models\WhatsAppUserMemory;
use App\Services\WhatsApp\WhatsAppComplianceService;
use Illuminate\Console\Command;

class WhatsAppPruneMemory extends Command
{
    protected $signature = 'whatsapp:prune-memory';

    protected $description = 'Clear long-term memory for users with no updates within retention period.';

    public function handle(WhatsAppComplianceService $compliance): int
    {
        $days = $compliance->retentionDaysMemory();
        $cutoff = now()->subDays($days);
        $updated = WhatsAppUserMemory::where('updated_at', '<', $cutoff)
            ->whereNotNull('memory_text')
            ->update(['memory_text' => null, 'updated_at' => now()]);
        $this->info("Cleared long-term memory for {$updated} user(s) (inactive > {$days} days).");
        return 0;
    }
}
