<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Databse\Eloquent\Relations\BelongsTo;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'loan_id',
        'user_id',
        'transaction_type',
        'amount',
        'phone_number',
        'mpesa_receipt',
        'checkout_request_id',
        'merchant_request_id',
        'status',
        'transaction_date',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
