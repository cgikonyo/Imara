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
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
            ->constrained()
            ->restrictOnDelete();

            $table->foreignId('loan_id')
            ->nullable()
            ->constrained()
            ->restrictOnDelete();

            $table->enum('transaction_type', [
                'disbursement',
                'repayment',
            ]);

            $table->decimal('amount', 12, 2);

            $table->string('phone_number');

            $table->string('mpesa_receipt')->nullable()->unique();

            $table->string('checkout_request_id')->nullable()->unique();

            $table->string('merchant_request_id')->nullable();

            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'cancelled',
            ])->default('pending');


            $table->timestamp('transaction_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
