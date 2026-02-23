<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankingAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action_type',
        'resource_type',
        'resource_id',
        'channel',
        'session_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
