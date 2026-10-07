<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use App\Services\RepaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RepaymentController extends Controller
{
    public function store(
        Request $request,
        RepaymentService $repaymentService
    ): JsonResponse {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $loan = Loan::findOrFail($validated['loan_id']);

        // Customers can only repay their own loans
        if ($loan->user_id !== $user->id && ! $user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to repay this loan.',
            ], 403);
        }

        if (! in_array($loan->status, ['disbursed', 'active', 'overdue'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This loan cannot receive repayments.',
            ], 422);
        }

        $repayment = $repaymentService->record(
            $loan,
            $user->id,
            (float) $validated['amount'],
            $validated['payment_method'],
            $validated['transaction_reference'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Repayment recorded successfully.',
            'data' => [
                'repayment' => $repayment,
                'loan' => $loan->fresh()->load('loanProduct'),
            ],
        ], 201);
    }

    public function initiateMpesa(
        Request $request,
        MpesaService $mpesaService
    ): JsonResponse {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'phone_number' => ['nullable', 'string', 'max:16'],
        ]);

        $user = $request->user();
        $loan = Loan::findOrFail($validated['loan_id']);

        if ($loan->user_id !== $user->id && ! $user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to repay this loan.',
            ], 403);
        }

        if (! in_array($loan->status, ['disbursed', 'active', 'overdue'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This loan cannot receive repayments.',
            ], 422);
        }

        $amount = (int) $validated['amount'];
        if ($amount > (float) $loan->outstanding_balance) {
            return response()->json([
                'success' => false,
                'message' => 'Repayment amount cannot exceed the outstanding balance.',
            ], 422);
        }

        $phoneNumber = $validated['phone_number'] ?? $user->phone;
        if (
            ! is_string($phoneNumber)
            || ! preg_match('/^(?:\+?254|0)?[17]\d{8}$/', $phoneNumber)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Provide a valid Kenyan M-Pesa phone number.',
            ], 422);
        }

        $digits = ltrim($phoneNumber, '+');
        $phoneNumber = str_starts_with($digits, '0')
            ? '254'.substr($digits, 1)
            : (str_starts_with($digits, '254') ? $digits : '254'.$digits);

        $transaction = DB::transaction(function () use (
            $loan,
            $user,
            $amount,
            $phoneNumber
        ) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if (! in_array($lockedLoan->status, ['disbursed', 'active', 'overdue'], true)) {
                throw ValidationException::withMessages([
                    'loan_id' => 'This loan cannot receive repayments.',
                ]);
            }

            if ($amount > (float) $lockedLoan->outstanding_balance) {
                return null;
            }

            $pending = MpesaTransaction::query()
                ->where('loan_id', $lockedLoan->id)
                ->where('transaction_type', 'repayment')
                ->where('status', 'pending')
                ->exists();

            if ($pending) {
                return false;
            }

            return MpesaTransaction::create([
                'loan_id' => $lockedLoan->id,
                'user_id' => $user->id,
                'transaction_type' => 'repayment',
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'status' => 'pending',
            ]);
        });

        if ($transaction === null) {
            return response()->json([
                'success' => false,
                'message' => 'Repayment amount cannot exceed the outstanding balance.',
            ], 422);
        }

        if ($transaction === false) {
            return response()->json([
                'success' => false,
                'message' => 'An M-Pesa repayment is already pending for this loan.',
            ], 409);
        }

        try {
            $response = $mpesaService->stkPush(
                $phoneNumber,
                $amount,
                'Loan'.$loan->id,
                'Loan repayment'
            );
        } catch (ConnectionException|\RuntimeException $exception) {
            $transaction->update(['status' => 'failed']);
            Log::error('M-Pesa STK Push could not be initiated.', [
                'mpesa_transaction_id' => $transaction->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'M-Pesa payment could not be initiated. Please try again.',
            ], 502);
        }

        $transaction->update([
            'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $response['MerchantRequestID'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'M-Pesa payment prompt sent. Await payment confirmation.',
            'data' => [
                'transaction' => $transaction->fresh(),
                'response_description' => $response['ResponseDescription'] ?? null,
            ],
        ], 202);
    }
}
