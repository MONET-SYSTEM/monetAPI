<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('current_balance', 15, 2)->after('initial_balance')->default(0);
        });

        // Update all existing accounts to have current_balance equal to initial_balance
        DB::statement('UPDATE accounts SET current_balance = initial_balance');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('current_balance');
        });
    }
};
