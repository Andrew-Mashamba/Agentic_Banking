<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppPendingTask extends Model
{
    protected $table = 'whatsapp_pending_tasks';

    protected $fillable = [
        'user_id',
        'phone_number',
        'task_type',
        'step',
        'context',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
