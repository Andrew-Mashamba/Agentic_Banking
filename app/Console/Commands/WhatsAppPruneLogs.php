<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class WhatsAppPruneLogs extends Command
{
    protected $signature = 'whatsapp:prune-logs';

    protected $description = 'Delete WhatsApp log files older than configured retention days.';

    public function handle(): int
    {
        $days = (int) config('whatsapp.log_retention_days', 90);
        $cutoff = now()->subDays($days)->startOfDay()->timestamp;
        $logPath = storage_path('logs');
        $deleted = 0;
        if (! is_dir($logPath)) {
            $this->info('Logs directory does not exist.');
            return 0;
        }
        foreach (File::glob($logPath . '/whatsapp*.log') as $path) {
            $file = new \SplFileInfo($path);
            if ($file->getMTime() < $cutoff) {
                if (File::delete($path)) {
                    $deleted++;
                }
            }
        }
        $this->info("Deleted {$deleted} WhatsApp log file(s) older than {$days} days.");
        return 0;
    }
}
