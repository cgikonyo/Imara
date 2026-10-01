<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanTransactionService
{
    public function record(
        Loan $loan,
        string $type,
        float $amount,
        ?string $description = null
    ): LoanTransaction {
        return DB::transaction(function () use (
            $loan,
            $type,
            $amount,
            $description
        ) {
            $loan->refresh();

            return LoanTransaction::create([
                'loan_id' => $loan->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $loan->outstanding_balance,
                'reference' => Str::uuid()->toString(),
                'description' => $description,
            ]);
        });
    }
}