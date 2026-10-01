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
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('min_amount', 12, 2);
            $table->decimal('max_amount', 12, 2);

            $table->decimal('interest_rate', 5, 2);

            $table->enum('interest_type', [
                'flat', 
                'reducing+balance',
            ]);

            $table->unsignedInteger('min_term');
            $table->unsignedInteger('max_term');

            $table->enum('term_unit', [
                'days',
                'weeks',
                'months',
                'years',
            ]);

            $table->decimal('processing_fee', 12, 2)->default(0);
            $table->decimal('penalty_fee', 12, 2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
