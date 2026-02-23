<?php

namespace App\Services\WhatsApp\AttachmentScanners;

use App\Contracts\AttachmentScannerInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ClamAvScanner implements AttachmentScannerInterface
{
    protected string $clamscanPath = 'clamscan';

    public function scan(string $fullPath): bool
    {
        if (! file_exists($fullPath)) {
            return false;
        }
        try {
            $process = new Process([$this->clamscanPath, '--no-summary', $fullPath]);
            $process->setTimeout(30);
            $process->run();
            if ($process->isSuccessful()) {
                return true;
            }
            $output = $process->getOutput() . $process->getErrorOutput();
            if (str_contains($output, 'FOUND') || str_contains($output, 'Infected')) {
                Log::channel('whatsapp')->warning('ClamAV: file infected', ['path' => $fullPath]);
                return false;
            }
            Log::channel('whatsapp')->warning('ClamAV: scan failed (treating as unsafe)', ['path' => $fullPath, 'output' => $output]);
            return false;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('ClamAV: exception', ['path' => $fullPath, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
