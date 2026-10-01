<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('interesting_amount', 'interest_amount');
            $table->renameColumn('outsatnding_balance', 'outstanding_balance');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('interest_amount', 'interesting_amount');
            $table->renameColumn('outstanding_balance', 'outsatnding_balance');
        });
    }
};