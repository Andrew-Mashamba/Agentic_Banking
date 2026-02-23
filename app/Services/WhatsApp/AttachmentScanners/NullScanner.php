<?php

namespace App\Services\WhatsApp\AttachmentScanners;

use App\Contracts\AttachmentScannerInterface;

class NullScanner implements AttachmentScannerInterface
{
    public function scan(string $fullPath): bool
    {
        return true;
    }
}
