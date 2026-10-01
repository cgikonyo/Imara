<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Repayment;
use App\Services\LoanTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepaymentController extends Controller
{
    public function store(
        Request $request,
        LoanTransactionService $transactionService
    ): JsonResponse {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,mpesa,bank'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $loan = Loan::findOrFail($validated['loan_id']);

        // Customers can only repay their own loans
        if ($loan->user_id !== $user->id && !$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to repay this loan.',
            ], 403);
        }

        if (!in_array($loan->status, ['disbursed', 'active', 'overdue'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This loan cannot receive repayments.',
            ], 422);
        }

        $amount = (float) $validated['amount'];

        if ($amount > (float) $loan->outstanding_balance) {
            return response()->json([
                'success' => false,
                'message' => 'Repayment amount cannot exceed the outstanding balance.',
            ], 422);
        }

        $repayment = DB::transaction(function () use (
            $loan,
            $user,
            $amount,
            $validated,
            $transactionService
        ) {
            $newAmountPaid = (float) $loan->amount_paid + $amount;

            $newOutstanding = max(
                0,
                (float) $loan->outstanding_balance - $amount
            );

            $newStatus = $newOutstanding == 0
                ? 'completed'
                : 'active';

            $loan->update([
                'amount_paid' => $newAmountPaid,
                'outstanding_balance' => $newOutstanding,
                'status' => $newStatus,
            ]);

            $repayment = Repayment::create([
                'loan_id' => $loan->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'transaction_reference' =>
                    $validated['transaction_reference'] ?? null,
                'paid_at' => now(),
            ]);

            $transactionService->record(
                $loan->fresh(),
                'repayment',
                $amount,
                'Loan repayment received.'
            );

            return $repayment;
        });

        return response()->json([
            'success' => true,
            'message' => 'Repayment recorded successfully.',
            'data' => [
                'repayment' => $repayment,
                'loan' => $loan->fresh()->load('loanProduct'),
            ],
        ], 201);
    }
}