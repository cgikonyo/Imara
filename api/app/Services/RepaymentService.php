<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MpesaTransaction;
use App\Models\Repayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RepaymentService
{
    public function __construct(
        private readonly LoanTransactionService $transactionService
    ) {}

    public function record(
        Loan $loan,
        int $userId,
        float $amount,
        string $paymentMethod,
        ?string $transactionReference
    ): Repayment {
        return DB::transaction(function () use (
            $loan,
            $userId,
            $amount,
            $paymentMethod,
            $transactionReference
        ) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if (! in_array($loan->status, ['disbursed', 'active', 'overdue'], true)) {
                throw ValidationException::withMessages([
                    'loan_id' => 'This loan cannot receive repayments.',
                ]);
            }

            if (
                $paymentMethod !== 'mpesa'
                && MpesaTransaction::query()
                    ->where('loan_id', $loan->id)
                    ->where('transaction_type', 'repayment')
                    ->where('status', 'pending')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'loan_id' => 'An M-Pesa repayment is pending for this loan.',
                ]);
            }

            if ($amount > (float) $loan->outstanding_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Repayment amount cannot exceed the outstanding balance.',
                ]);
            }

            $newAmountPaid = (float) $loan->amount_paid + $amount;
            $newOutstanding = max(
                0,
                (float) $loan->outstanding_balance - $amount
            );

            $loan->update([
                'amount_paid' => $newAmountPaid,
                'outstanding_balance' => $newOutstanding,
                'status' => $newOutstanding == 0 ? 'completed' : 'active',
            ]);

            $repayment = Repayment::create([
                'loan_id' => $loan->id,
                'user_id' => $userId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_reference' => $transactionReference ?? Str::uuid()->toString(),
                'paid_at' => now(),
            ]);

            $this->transactionService->record(
                $loan->fresh(),
                'repayment',
                $amount,
                'Loan repayment received.'
            );

            return $repayment;
        });
    }
}
