<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'last_four',
        'expiry_date',
        'type',
        'status',
        'pin_set_at',
        'daily_limit_amount',
        'online_enabled',
        'international_enabled',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'pin_set_at' => 'datetime',
            'daily_limit_amount' => 'decimal:4',
            'online_enabled' => 'boolean',
            'international_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
