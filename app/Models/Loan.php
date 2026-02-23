<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'loan_product_id',
        'amount',
        'outstanding_balance',
        'interest_rate',
        'status',
        'disbursed_at',
        'maturity_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'outstanding_balance' => 'decimal:4',
            'interest_rate' => 'decimal:4',
            'disbursed_at' => 'datetime',
            'maturity_date' => 'date',
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

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function repaymentSchedules(): HasMany
    {
        return $this->hasMany(LoanRepaymentSchedule::class, 'loan_id');
    }
}
