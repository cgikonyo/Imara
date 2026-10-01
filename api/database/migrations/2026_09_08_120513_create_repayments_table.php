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
        Schema::create('repayments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')
            ->constrained()
            ->restrictOnDelete();

            $table->foreignId('user_id')
            ->constrained()
            ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('payment_method');

            $table->string('transaction_reference')->unique();

            $table->timestamp('paid_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayments');
    }
};
