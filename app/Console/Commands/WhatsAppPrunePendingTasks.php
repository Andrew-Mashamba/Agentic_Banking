<?php

namespace App\Console\Commands;

use App\Models\WhatsAppPendingTask;
use App\Services\WhatsApp\WhatsAppComplianceService;
use Illuminate\Console\Command;

class WhatsAppPrunePendingTasks extends Command
{
    protected $signature = 'whatsapp:prune-pending-tasks';

    protected $description = 'Mark pending tasks older than TTL as abandoned (compliance).';

    public function handle(WhatsAppComplianceService $compliance): int
    {
        $days = $compliance->retentionDaysPendingTasks();
        $cutoff = now()->subDays($days);
        $updated = WhatsAppPendingTask::where('status', WhatsAppPendingTask::STATUS_PENDING)
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => WhatsAppPendingTask::STATUS_ABANDONED]);
        $this->info("Marked {$updated} pending tasks as abandoned (older than {$days} days).");
        return 0;
    }
}
