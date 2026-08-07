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
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // asset, liability, revenue, expense, equity
            $table->string('code')->unique()->nullable(); // System code like system_cash, reserve_fund
            $table->string('currency')->default('XOF');
            
            // Polymorphic relation to link to a User, Shop, etc. (Nullable for system accounts)
            $table->nullableMorphs('owner');
            
            // Sub-type for wallets: available, escrow, reserve
            $table->string('wallet_type')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
