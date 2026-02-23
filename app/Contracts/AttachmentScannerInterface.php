<?php

namespace App\Contracts;

/**
 * Scan uploaded attachments for malware. Return true if clean, false if infected (or scan failed).
 */
interface AttachmentScannerInterface
{
    public function scan(string $fullPath): bool;
}
