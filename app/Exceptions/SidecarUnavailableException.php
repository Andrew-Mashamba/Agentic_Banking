<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the AI sidecar is unavailable (5xx, timeout, 429, circuit open).
 * Job should retry with backoff.
 */
class SidecarUnavailableException extends Exception
{
}
