<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentSchedule extends Model
{
    protected $fillable = [
        'loan_id',
        'due_date',
        'principal_amount',
        'interest_amount',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_amount' => 'decimal:4',
            'interest_amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
