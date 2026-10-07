<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MpesaService
{
    /**
     * Get the Daraja OAuth access token.
     */
    public function getAccessToken(): string
    {
        $baseUrl = config('services.mpesa.environment') === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';

        $response = Http::withBasicAuth(
            config('services.mpesa.consumer_key'),
            config('services.mpesa.consumer_secret')
        )->get($baseUrl.'/oauth/v1/generate', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Daraja authentication failed: '.$response->body()
            );
        }

        $token = $response->json('access_token');

        if (! $token) {
            throw new \RuntimeException(
                'Daraja authentication succeeded but no access token was returned.'
            );
        }

        return $token;
    }

    /**
     * Initiate an M-Pesa STK Push.
     */
    public function stkPush(
        string $phone,
        int $amount,
        string $accountReference,
        string $transactionDesc
    ): array {
        $baseUrl = config('services.mpesa.environment') === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';

        $shortcode = config('services.mpesa.shortcode');
        $passkey = config('services.mpesa.passkey');
        $callbackUrl = config('services.mpesa.callback_url');

        if (! $shortcode || ! $passkey || ! $callbackUrl) {
            throw new \RuntimeException(
                'M-Pesa shortcode, passkey, or callback URL is not configured.'
            );
        }

        $timestamp = now()->format('YmdHis');

        $password = base64_encode(
            $shortcode.$passkey.$timestamp
        );

        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post($baseUrl.'/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $phone,
                'PartyB' => $shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => $accountReference,
                'TransactionDesc' => $transactionDesc,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'STK Push request failed: '.$response->body()
            );
        }

        $data = $response->json();
        if (
            ! is_array($data)
            || (string) ($data['ResponseCode'] ?? '') !== '0'
            || empty($data['CheckoutRequestID'])
            || empty($data['MerchantRequestID'])
        ) {
            throw new \RuntimeException(
                'STK Push request did not return valid request identifiers.'
            );
        }

        return $data;
    }
}
