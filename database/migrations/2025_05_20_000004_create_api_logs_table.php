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
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->string('method', 10);
            $table->string('url');
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->json('request_payload')->nullable();
            $table->integer('response_code');
            $table->json('response_body')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->float('duration', 8, 4)->nullable()->comment('Duration in seconds');
            $table->enum('status', ['success', 'error', 'warning'])->default('success');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
