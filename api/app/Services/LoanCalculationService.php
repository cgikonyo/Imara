<?php

namespace App\Services;

use App\Models\LoanProduct;

class LoanCalculationService
{
    public function calculate(
        LoanProduct $product,
        float $principal,
        int $term
    ): array {
        $interestRate = (float) $product->interest_rate;
        $processingFee = (float) $product->processing_fee;

        if ($product->interest_type === 'flat') {
            return $this->calculateFlat(
                $principal,
                $interestRate,
                $processingFee
            );
        }

        return $this->calculateReducingBalance(
            $principal,
            $interestRate,
            $term,
            $processingFee
        );
    }

    private function calculateFlat(
        float $principal,
        float $annualRate,
        float $processingFee
    ): array {
        $interest = $principal * ($annualRate / 100);

        $total = $principal + $interest + $processingFee;

        return [
            'interest_amount' => round($interest, 2),
            'processing_fee' => round($processingFee, 2),
            'total_amount' => round($total, 2),
            'installment_amount' => round($total, 2),
            'number_of_installments' => 1,
        ];
    }

    private function calculateReducingBalance(
        float $principal,
        float $annualRate,
        int $term,
        float $processingFee
    ): array {
        // Temporary monthly amortization calculation.
        $monthlyRate = ($annualRate / 100) / 12;

        if ($monthlyRate == 0) {
            $installment = $principal / $term;
        } else {
            $installment = $principal *
                ($monthlyRate * pow(1 + $monthlyRate, $term)) /
                (pow(1 + $monthlyRate, $term) - 1);
        }

        $totalRepayment = $installment * $term;
        $interest = $totalRepayment - $principal;
        $total = $totalRepayment + $processingFee;

        return [
            'interest_amount' => round($interest, 2),
            'processing_fee' => round($processingFee, 2),
            'total_amount' => round($total, 2),
            'installment_amount' => round($installment, 2),
            'number_of_installments' => $term,
        ];
    }
}