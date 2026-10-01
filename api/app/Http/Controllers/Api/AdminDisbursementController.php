<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDisbursementController extends Controller
{
   public function disburse(
    Loan $loan,
    LoanTransactionService $transactionService
   ): JsonResponse {
    if($loan->status !== 'approved'){
        return response()->json([
            'success' => false,
            'message' => 'Only approved loans can be disbursed.' ,
        ], 422);
    }

    DB:: transaction(function () use ($loan, $transactionService){
        $disbursedAt = now();

        $dueDate = match ($loan->term_unit) {
            'days' => $disbursedAt->copy()->addDays($loan->term),
            'weeks' => $disbursedAt->copy()->addweeks($loan->term),
            'months' => $disbursedAt->copy()->addMonths($loan->term),
            default => throw new \InvalidArgumentException(
                'Unsupported loan term unit.'
            ),
        };

        $loan->update([
            'status' => 'disbursed',
            'disbursed_at' => $disbursedAt,
            'due_date' => $dueDate,
        ]);

        $transactionService->record(
            $loan->fresh(),
            'disbursement',
            $loan->amount,
            'Loan disbursed to customer.'
        );
    });

    return response()->json([
        'success' => true,
        'message' => 'Loan disbursed successfully.',
        'data' => $loan->fresh()->load('loanProduct'),
    ]);

   }
}
