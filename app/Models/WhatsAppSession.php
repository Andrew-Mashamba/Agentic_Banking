<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'phone_number',
        'state',
        'data',
        'guest_id', // reused as user_id for banking
        'last_activity_at',
    ];

    protected $casts = [
        'data' => 'array',
        'last_activity_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            $session->last_activity_at ??= now();
        });
    }

    /**
     * The user associated with this session (uses guest_id column for compatibility).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function isExpired(): bool
    {
        if (!$this->last_activity_at) {
            return true;
        }

        $timeout = config('whatsapp.session_timeout', 3600);

        return $this->last_activity_at->diffInSeconds(now()) > $timeout;
    }
}
