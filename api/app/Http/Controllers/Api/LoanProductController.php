<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $products = LoanProduct::where('is_active', true)
            ->orderBy('min_amount')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'min:0', 'gt:min_amount'],

            'interest_rate' => ['required', 'numeric', 'min:0'],
            'interest_type' => [
                'required', 
                'in:flat,reducing_balance'
            ],

            'min_term' => ['required', 'integer', 'min:1'],
            'max_term' => ['required', 'integer', 'min:1', 'gte:min_term'],
            
            'term_unit' => [
                'required', 
                'in:days,weeks,months'
            ],

            'processing_fee' => ['nullable', 'numeric', 'min:0'],
            'penalty_fee' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = LoanProduct::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Loan product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(LoanProduct $loanProduct): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $loanProduct,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LoanProduct $loanProduct): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],

            'min_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'required', 'numeric'],

            'interest_rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'interest_type' => [
                'sometimes', 
                'in:flat,reducing_balance'
            ],

            'min_term' => ['sometimes', 'required', 'integer', 'min:1'],
            'max_term' => ['sometimes', 'required', 'integer', 'min:1'],

            'term_unit' => [
                'sometimes',
                'in:days,weeks,months'
            ],

            'processing_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'penalty_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            'is_active' => ['sometimes', 'boolean'],
        ]);

        $loanProduct->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Loan product updated successfully',
            'data' => $loanProduct->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LoanProduct $loanProduct): JsonResponse
    {
        $loanProduct->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Loan product deactivated successfully',
        ]);
    }
}