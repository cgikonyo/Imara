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
        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('approval_at', 'approved_at');
            $table->renameColumn('disbursement_at', 'disbursed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('approved_at', 'approval_at');
            $table->renameColumn('disbursed_at', 'disbursement_at');
        });
    }
};
