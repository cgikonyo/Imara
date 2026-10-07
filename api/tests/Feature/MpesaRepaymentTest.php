<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\MpesaTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MpesaRepaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_mpesa_repayment_starts_as_pending_without_changing_loan_balance(): void
    {
        [$user, $loan] = $this->createLoan();
        Sanctum::actingAs($user);

        config([
            'services.mpesa.environment' => 'sandbox',
            'services.mpesa.consumer_key' => 'consumer-key',
            'services.mpesa.consumer_secret' => 'consumer-secret',
            'services.mpesa.shortcode' => '174379',
            'services.mpesa.passkey' => 'passkey',
            'services.mpesa.callback_url' => 'https://example.test/api/mpesa/callback',
        ]);

        Http::fake([
            '*sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
                'access_token' => 'test-token',
            ]),
            '*sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest*' => Http::response([
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CheckoutRequestID' => 'ws_CO_123',
                'MerchantRequestID' => 'merchant_123',
            ]),
        ]);

        $response = $this->postJson('/api/repayments/mpesa', [
            'loan_id' => $loan->id,
            'amount' => 250,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.transaction.status', 'pending')
            ->assertJsonPath('data.transaction.checkout_request_id', 'ws_CO_123');

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
        ]);
        $this->assertDatabaseCount('repayments', 0);
        $this->assertDatabaseHas('mpesa_transactions', [
            'loan_id' => $loan->id,
            'phone_number' => '254712345678',
            'amount' => 250,
            'status' => 'pending',
        ]);
    }

    public function test_mpesa_repayment_rejects_decimal_amounts(): void
    {
        [$user, $loan] = $this->createLoan();
        Sanctum::actingAs($user);

        $this->postJson('/api/repayments/mpesa', [
            'loan_id' => $loan->id,
            'amount' => 10.50,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('mpesa_transactions', 0);
    }

    public function test_mpesa_repayment_rejects_a_second_pending_request_for_the_loan(): void
    {
        [$user, $loan] = $this->createLoan();
        $this->createPendingTransaction($user, $loan);
        Sanctum::actingAs($user);

        $this->postJson('/api/repayments/mpesa', [
            'loan_id' => $loan->id,
            'amount' => 250,
        ])->assertStatus(409);

        $this->assertDatabaseCount('mpesa_transactions', 1);
    }

    public function test_cash_repayment_cannot_spend_a_balance_reserved_by_pending_mpesa(): void
    {
        [$user, $loan] = $this->createLoan();
        $this->createPendingTransaction($user, $loan);
        Sanctum::actingAs($user);

        $this->postJson('/api/repayments', [
            'loan_id' => $loan->id,
            'amount' => 250,
            'payment_method' => 'cash',
            'transaction_reference' => 'cash-reference',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('repayments', 0);
        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'outstanding_balance' => 1000,
        ]);
    }

    public function test_successful_callback_records_repayment_once(): void
    {
        [$user, $loan] = $this->createLoan();
        $transaction = $this->createPendingTransaction($user, $loan);
        $callback = $this->successCallback();

        $this->postJson('/api/mpesa/callback', $callback)->assertOk();
        $this->postJson('/api/mpesa/callback', $callback)->assertOk();

        $this->assertDatabaseCount('repayments', 1);
        $this->assertDatabaseHas('repayments', [
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'amount' => 250,
            'payment_method' => 'mpesa',
            'transaction_reference' => 'ABC123XYZ',
        ]);
        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'amount_paid' => 250,
            'outstanding_balance' => 750,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('mpesa_transactions', [
            'id' => $transaction->id,
            'status' => 'completed',
            'mpesa_receipt' => 'ABC123XYZ',
        ]);
        $this->assertDatabaseCount('loan_transactions', 1);
    }

    public function test_failed_callback_does_not_record_a_repayment(): void
    {
        [$user, $loan] = $this->createLoan();
        $transaction = $this->createPendingTransaction($user, $loan);

        $this->postJson('/api/mpesa/callback', [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => $transaction->checkout_request_id,
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('mpesa_transactions', [
            'id' => $transaction->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseCount('repayments', 0);
        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'outstanding_balance' => 1000,
        ]);
    }

    public function test_unknown_callback_is_acknowledged_without_creating_a_repayment(): void
    {
        $this->postJson('/api/mpesa/callback', $this->successCallback('unknown_checkout'))
            ->assertOk()
            ->assertJsonPath('ResultCode', 0);

        $this->assertDatabaseCount('repayments', 0);
    }

    private function createLoan(): array
    {
        $user = User::factory()->create([
            'phone' => '0712345678',
            'is_admin' => false,
        ]);

        $now = now();
        $productId = DB::table('loan_products')->insertGetId([
            'name' => 'Test loan',
            'min_amount' => 100,
            'max_amount' => 5000,
            'interest_rate' => 0,
            'interest_type' => 'flat',
            'min_term' => 1,
            'max_term' => 12,
            'term_unit' => 'months',
            'processing_fee' => 0,
            'penalty_fee' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $loanId = DB::table('loans')->insertGetId([
            'user_id' => $user->id,
            'loan_product_id' => $productId,
            'amount' => 1000,
            'interest_rate' => 0,
            'interest_type' => 'flat',
            'interest_amount' => 0,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'term' => 1,
            'term_unit' => 'months',
            'status' => 'active',
            'application_date' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$user, Loan::findOrFail($loanId)];
    }

    private function createPendingTransaction(User $user, Loan $loan): MpesaTransaction
    {
        return MpesaTransaction::create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'transaction_type' => 'repayment',
            'amount' => 250,
            'phone_number' => '254712345678',
            'checkout_request_id' => 'checkout_123',
            'merchant_request_id' => 'merchant_123',
            'status' => 'pending',
        ]);
    }

    private function successCallback(string $checkoutRequestId = 'checkout_123'): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 250],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123XYZ'],
                            ['Name' => 'TransactionDate', 'Value' => '20261007123000'],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];
    }
}
