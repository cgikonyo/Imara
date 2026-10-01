<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')
            ->constrained()
            ->restrictOnDelete();

            $table->enum('type', [
                'disbursement',
                'repayment',
                'interest',
                'fee',
                'late_fee',
                'adjustment',
            ]);

            $table->decimal('amount', 12, 2);

            $table->decimal('balance_after', 12, 2);

            $table->string('reference')->nullable()->unique();

            $table->text('description')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_transactions');
    }
};
