<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function store(
        Request $request,
        LoanCalculationService $calculationService
    ): JsonResponse {
        $validated = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'term' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        $product = LoanProduct::where('id', $validated['loan_product_id'])
            ->where('is_active', true)
            ->first();

            if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Loan product is not available.',
            ], 404);
            }

        $amount = (float) $validated['amount'];
        $term = (int) $validated['term'];

        // Validate amount against product limits
        if ($amount < $product->min_amount || $amount > $product->max_amount) {
            return response()->json([
                'success' => false,
                'message' => "Loan amount must be between {$product->min_amount} and {$product->max_amount}.",
            ], 422);
        }

        // Validate term against product limits
        if ($term < $product->min_term || $term > $product->max_term) {
            return response()->json([
                'success' => false,
                'message' => "Loan term must be between {$product->min_term} and {$product->max_term} {$product->term_unit}.",
            ], 422);
        }

        $calculation = $calculationService->calculate(
            $product,
            $amount,
            $term
        );

        $loan = Loan::create([
            'user_id' => $user->id,
            'loan_product_id' => $product->id,

            'amount' => $amount,

            // Snapshot the product terms at application time.
            'interest_rate' => $product->interest_rate,
            'interest_type' => $product->interest_type,

            'interest_amount' => $calculation['interest_amount'],
            'processing_fee' => $calculation['processing_fee'],

            'total_amount' => $calculation['total_amount'],
            'amount_paid' => 0,
            'outstanding_balance' => $calculation['total_amount'],

            'term' => $term,
            'term_unit' => $product->term_unit,

            'status' => 'pending',
            'application_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully.',
            'data' => $loan->load('loanProduct'),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $loans = Loan::where('user_id', $request->user()->id)
        ->with('loanProduct')
        ->latest()
        ->get();

        return response()->json([
            'success' => true,
            'data' => $loans,
        ]);
    }

    public function show(Request $request, Loan $loan): JsonResponse
    {
        //customers can only view their own loans
        if($loan->user_id !== $request->user()->id && !$request->user()->is_admin) {
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to view this loan.',
        ], 403);
        }

        $loan->load([
        'loanProduct',
        'repayments',
        'transactions',
        ]);

        return response()->json([
        'success' => true,
        'data' => $loan,
        ]);
    }
}