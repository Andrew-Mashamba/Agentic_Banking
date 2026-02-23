<?php

namespace App\Services;

use App\Models\BankingAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit log for banking actions (compliance and AI-driven actions).
 */
class BankingAuditService
{
    public static function log(
        string $actionType,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $metadata = null,
        ?string $channel = null,
        ?string $sessionId = null,
        ?int $userId = null
    ): BankingAuditLog {
        $userId = $userId ?? Auth::id();
        $channel = $channel ?? request()?->attributes->get('audit_channel', 'api');

        return BankingAuditLog::create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'channel' => $channel,
            'session_id' => $sessionId,
            'metadata' => $metadata ? self::sanitizeMetadata($metadata) : null,
        ]);
    }

    /** Remove PII from metadata (e.g. full account numbers, names). */
    protected static function sanitizeMetadata(array $meta): array
    {
        $allowed = ['amount', 'currency', 'type', 'reference', 'status', 'last_four', 'amount_requested', 'tenor_months'];
        $out = [];
        foreach ($meta as $k => $v) {
            if (in_array($k, $allowed, true) && is_scalar($v)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
