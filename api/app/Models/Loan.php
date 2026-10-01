<?php

namespace App\Models;

use App\Models\LoanTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'user_id',
        'loan_product_id',
        'amount',
        'interest_rate',
        'intetrest_type',
        'interest_amount',
        'processing_fee',
        'total_amount',
        'amount_paid',
        'outstanding_balance',
        'term',
        'term_unit',
        'status',
        'application_date',
        'approved_at',
        'disbursed_at',
        'due_date',
    ];

    protected $casts = [
        'application_date' => 'datetime',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'due_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoanTransaction::class);
    }

    public function mpesaTransactions(): HasMany
    {
        return $this->hasMany(MpesaTransaction::class);
    }
}
