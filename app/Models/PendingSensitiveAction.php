<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingSensitiveAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action_type',
        'payload',
        'otp_hash',
        'token',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
