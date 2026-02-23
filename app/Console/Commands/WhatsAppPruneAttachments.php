<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppComplianceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class WhatsAppPruneAttachments extends Command
{
    protected $signature = 'whatsapp:prune-attachments';

    protected $description = 'Delete WhatsApp attachment files older than configured retention.';

    public function handle(WhatsAppComplianceService $compliance): int
    {
        $days = $compliance->retentionDaysAttachments();
        $cutoff = now()->subDays($days)->timestamp;
        $basePath = config('whatsapp.attachments.storage_path', 'whatsapp_attachments');
        $fullPath = str_starts_with($basePath, '/') || preg_match('#^[A-Za-z]:#', $basePath)
            ? rtrim($basePath, '/')
            : storage_path('app/' . trim($basePath, '/'));

        if (! is_dir($fullPath)) {
            $this->info('Attachments directory does not exist.');
            return 0;
        }

        $deleted = $this->pruneDirectory($fullPath, $cutoff);
        $this->info("Deleted {$deleted} attachment file(s) older than {$days} days.");
        return 0;
    }

    private function pruneDirectory(string $dir, int $cutoffTimestamp): int
    {
        $count = 0;
        foreach (File::allFiles($dir) as $file) {
            if ($file->getMTime() < $cutoffTimestamp) {
                if (File::delete($file->getPathname())) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
