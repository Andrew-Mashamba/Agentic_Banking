<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'beneficiary_id',
        'amount',
        'currency',
        'type',
        'status',
        'reference',
        'swift_details',
        'meta',
        'scheduled_at',
        'executed_at',
        'approval_workflow_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'swift_details' => 'array',
            'meta' => 'array',
            'scheduled_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }
}
