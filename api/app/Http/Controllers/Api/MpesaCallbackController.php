<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MpesaPaymentProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function handle(Request $request, MpesaPaymentProcessor $paymentProcessor): JsonResponse
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

        if (! is_array($callback)) {
            Log::warning('Invalid M-Pesa callback received.');

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Invalid callback payload.',
            ], 400);
        }

        try {
            $paymentProcessor->processCallback($callback);
        } catch (ConnectionException|\RuntimeException $exception) {
            Log::error('M-Pesa callback could not be verified with STK Query.', [
                'checkout_request_id' => $checkoutRequestId,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Callback could not be verified. Please retry.',
            ], 503);
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback processed successfully.',
        ]);
    }
}
