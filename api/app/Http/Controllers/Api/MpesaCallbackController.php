<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use App\Services\RepaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function handle(Request $request, RepaymentService $repaymentService): JsonResponse
    {
        $callback = $request->input('Body.stkCallback');
        $checkoutRequestId = is_array($callback)
            ? ($callback['CheckoutRequestID'] ?? null)
            : null;
        $resultCode = is_array($callback)
            ? ($callback['ResultCode'] ?? null)
            : null;

        if (! is_string($checkoutRequestId) || ! is_numeric($resultCode)) {
            Log::warning('Invalid M-Pesa callback received.');

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Invalid callback payload.',
            ], 400);
        }

        DB::transaction(function () use (
            $callback,
            $checkoutRequestId,
            $resultCode,
            $repaymentService
        ) {
            $transaction = MpesaTransaction::query()
                ->where('checkout_request_id', $checkoutRequestId)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                Log::warning('M-Pesa callback references an unknown transaction.', [
                    'checkout_request_id' => $checkoutRequestId,
                ]);

                return;
            }

            if ($transaction->status !== 'pending') {
                return;
            }

            if ($transaction->transaction_type !== 'repayment') {
                Log::warning('M-Pesa repayment callback references a non-repayment transaction.', [
                    'mpesa_transaction_id' => $transaction->id,
                ]);

                return;
            }

            if ((int) $resultCode !== 0) {
                $transaction->update([
                    'status' => (int) $resultCode === 1032 ? 'cancelled' : 'failed',
                ]);

                return;
            }

            $metadataItems = $callback['CallbackMetadata']['Item'] ?? [];
            $metadata = collect($metadataItems)->keyBy('Name');
            $receipt = $metadata->get('MpesaReceiptNumber')['Value'] ?? null;
            $paidAmount = $metadata->get('Amount')['Value'] ?? null;

            if (
                ! is_string($receipt)
                || $receipt === ''
                || ! is_numeric($paidAmount)
                || (float) $paidAmount !== (float) $transaction->amount
            ) {
                $transaction->update(['status' => 'failed']);
                Log::error('M-Pesa success callback did not match its pending transaction.', [
                    'mpesa_transaction_id' => $transaction->id,
                ]);

                return;
            }

            $dateValue = $metadata->get('TransactionDate')['Value'] ?? null;
            $transactionDate = is_numeric($dateValue)
                && Carbon::hasFormat((string) $dateValue, 'YmdHis')
                    ? Carbon::createFromFormat('YmdHis', (string) $dateValue)
                    : now();

            $repaymentService->record(
                $transaction->loan,
                $transaction->user_id,
                (float) $transaction->amount,
                'mpesa',
                $receipt
            );

            $transaction->update([
                'mpesa_receipt' => $receipt,
                'status' => 'completed',
                'transaction_date' => $transactionDate,
            ]);
        });

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback processed successfully.',
        ]);
    }
}
