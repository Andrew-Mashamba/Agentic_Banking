<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplication extends Model
{
    protected $table = 'loan_applications';

    protected $fillable = [
        'user_id',
        'loan_product_id',
        'amount_requested',
        'tenor_months',
        'purpose',
        'status',
        'eligibility_result',
        'approved_at',
        'loan_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:4',
            'eligibility_result' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
