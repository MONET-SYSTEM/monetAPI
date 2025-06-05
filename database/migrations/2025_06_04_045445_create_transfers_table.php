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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('source_transaction_id');
            $table->unsignedBigInteger('destination_transaction_id');
            $table->decimal('exchange_rate', 12, 6)->default(1.0);
            $table->boolean('used_real_time_rate')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('source_transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->foreign('destination_transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            
            // Indexes for better performance
            $table->index(['source_transaction_id', 'destination_transaction_id']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
