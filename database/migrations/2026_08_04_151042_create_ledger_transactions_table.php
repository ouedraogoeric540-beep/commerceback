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
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type')->nullable(); // Model class like Order, Withdrawal
            $table->unsignedBigInteger('reference_id')->nullable(); // ID
            $table->string('status')->default('completed'); // pending, completed, failed
            $table->string('description')->nullable();
            
            // For webhook idempotency
            $table->string('idempotency_key')->unique()->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
