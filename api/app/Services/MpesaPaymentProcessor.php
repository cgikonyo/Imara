<?php

namespace App\Services;

use App\Models\MpesaTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaPaymentProcessor
{
    public function __construct(
        private readonly MpesaService $mpesaService,
        private readonly RepaymentService $repaymentService
    ) {}

    /**
     * Process a Safaricom callback only after verifying its request with STK Query.
     *
     * @param  array<string, mixed>  $callback
     */
    public function processCallback(array $callback): ?MpesaTransaction
    {
        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        if (! is_string($checkoutRequestId) || $checkoutRequestId === '') {
            return null;
        }

        $transaction = MpesaTransaction::query()
            ->where('checkout_request_id', $checkoutRequestId)
            ->first();

        if (! $transaction || $transaction->transaction_type !== 'repayment') {
            return null;
        }

        if ($transaction->status !== 'pending') {
            return $transaction;
        }

        $query = $this->mpesaService->stkQuery($checkoutRequestId);

        return $this->applyQueryResult($transaction, $query, $callback);
    }

    public function reconcile(MpesaTransaction $transaction): MpesaTransaction
    {
        if ($transaction->transaction_type !== 'repayment') {
            throw new \InvalidArgumentException(
                'Only M-Pesa repayment transactions can be reconciled.'
            );
        }

        if ($transaction->status !== 'pending') {
            return $transaction;
        }

        if (! $transaction->checkout_request_id) {
            throw new \RuntimeException(
                'Pending M-Pesa transaction has no checkout request ID.'
            );
        }

        $query = $this->mpesaService->stkQuery($transaction->checkout_request_id);

        return $this->applyQueryResult($transaction, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $callback
     */
    private function applyQueryResult(
        MpesaTransaction $transaction,
        array $query,
        ?array $callback = null
    ): MpesaTransaction {
        return DB::transaction(function () use ($transaction, $query, $callback) {
            $transaction = MpesaTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($transaction->status !== 'pending') {
                return $transaction;
            }

            $resultCode = $query['ResultCode'] ?? null;
            if (! is_numeric($resultCode)) {
                return $transaction;
            }

            if ((int) $resultCode !== 0) {
                $transaction->update([
                    'status' => (int) $resultCode === 1032 ? 'cancelled' : 'failed',
                ]);

                return $transaction->fresh();
            }

            $metadata = collect($callback['CallbackMetadata']['Item'] ?? [])
                ->filter(fn ($item) => is_array($item) && isset($item['Name']))
                ->keyBy('Name');
            $receipt = $metadata->get('MpesaReceiptNumber')['Value'] ?? null;
            $paidAmount = $metadata->get('Amount')['Value'] ?? null;
            $paidPhone = $metadata->get('PhoneNumber')['Value'] ?? null;

            if (
                $callback !== null
                && (
                    ! is_string($receipt)
                    || $receipt === ''
                    || ! is_numeric($paidAmount)
                    || (float) $paidAmount !== (float) $transaction->amount
                    || ! is_numeric($paidPhone)
                    || (string) $paidPhone !== $transaction->phone_number
                )
            ) {
                Log::error('Verified M-Pesa callback metadata did not match the pending transaction.', [
                    'mpesa_transaction_id' => $transaction->id,
                ]);

                return $transaction;
            }

            $dateValue = $metadata->get('TransactionDate')['Value'] ?? null;
            $transactionDate = is_numeric($dateValue)
                && Carbon::hasFormat((string) $dateValue, 'YmdHis')
                    ? Carbon::createFromFormat('YmdHis', (string) $dateValue)
                    : now();

            $this->repaymentService->record(
                $transaction->loan,
                $transaction->user_id,
                (float) $transaction->amount,
                'mpesa',
                is_string($receipt) && $receipt !== ''
                    ? $receipt
                    : $transaction->checkout_request_id
            );

            $transaction->update([
                'mpesa_receipt' => is_string($receipt) ? $receipt : null,
                'status' => 'completed',
                'transaction_date' => $transactionDate,
            ]);

            return $transaction->fresh();
        });
    }
}
