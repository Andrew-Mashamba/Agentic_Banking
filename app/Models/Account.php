<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'account_number',
        'currency',
        'balance',
        'nickname',
        'is_primary',
        'status',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
            'is_primary' => 'boolean',
            'opened_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
