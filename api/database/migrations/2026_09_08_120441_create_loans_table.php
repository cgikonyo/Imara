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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
            ->constrained()
            ->cascadeonDelete();

            $table->foreignId('loan_product_id')
            ->constrained('loan_products')
            ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            //snapshot of the terms when the loan was created
            $table->decimal('interest_rate', 5, 2);
            $table->enum('interest_type', [
                'flat',
                'reducing+balance',
            ]);

            $table->decimal('interesting_amount', 12, 2)->default(0);
            $table->decimal('processing_fee',12, 2)->default(0);

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('outsatnding_balance', 12, 2)->default(0);

            $table->unsignedInteger('term');
            $table->enum('term_unit', [
                'days',
                'weeks',
                'months',
                'years'
            ]);

            $table->enum('status',[
                'pending',
                'approved',
                'rejected',
                'disbursed',
                'active',
                'completed',
                'overdue',
                'defaulted',
                'cancelled',
            ])->default('pending');

            $table->timestamp('application_date')->nullable();
            $table->timestamp('approval_at')->nullable();
            $table->timestamp('disbursement_at')->nullable();
            $table->timestamp('due_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
