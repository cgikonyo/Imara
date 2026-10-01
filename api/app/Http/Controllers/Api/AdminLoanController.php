<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminLoanController extends Controller
{
    public function approve(Loan $loan): JsonResponse
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending loans can be approved.',
            ], 422);
        }

        DB::transaction(function () use ($loan) {
            $loan->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Loan approved successfully.',
            'data' => $loan->fresh()->load('loanProduct'),
        ]);
    }

    public function reject(Loan $loan): JsonResponse
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending loans can be rejected.',
            ], 422);
        }

        $loan->update([
            'status' => 'rejected',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan rejected successfully.',
            'data' => $loan->fresh(),
        ]);
    }
}