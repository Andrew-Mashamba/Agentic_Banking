<?php

namespace App\Console\Commands;

use App\Models\BankingAuditLog;
use App\Services\WhatsApp\WhatsAppComplianceService;
use Illuminate\Console\Command;

class WhatsAppPruneAuditLogs extends Command
{
    protected $signature = 'whatsapp:prune-audit-logs';

    protected $description = 'Delete banking_audit_logs older than configured retention years (compliance).';

    public function handle(WhatsAppComplianceService $compliance): int
    {
        $years = $compliance->auditLogRetentionYears();
        $cutoff = now()->subYears($years);
        $deleted = BankingAuditLog::where('created_at', '<', $cutoff)->delete();
        $this->info("Pruned {$deleted} audit log rows older than {$years} years.");
        return 0;
    }
}
