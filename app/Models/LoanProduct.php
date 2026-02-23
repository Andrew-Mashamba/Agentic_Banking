<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    protected $fillable = [
        'name',
        'min_amount',
        'max_amount',
        'interest_rate',
        'tenor_months',
        'eligibility_rules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:4',
            'max_amount' => 'decimal:4',
            'interest_rate' => 'decimal:4',
            'is_active' => 'boolean',
            'eligibility_rules' => 'array',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'loan_product_id');
    }
}
