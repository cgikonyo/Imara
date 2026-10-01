<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    protected $fillable = [
        'name',
        'description',
        'interest_rate',
        'max_amount',
        'min_amount',
        'max_term',
        'min_term',
        'interest_type',
        'term_unit',
        'processing_fee',
        'penalty_fee',
        'is_active',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
