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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('code')->index(); // ex: SUMMER20
            $table->enum('type', ['percentage', 'fixed']); // type de remise
            $table->decimal('value', 10, 2); // valeur de la remise
            $table->decimal('min_amount', 10, 2)->nullable(); // montant minimum de la commande
            $table->integer('max_uses')->nullable(); // null = illimité
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Un code doit être unique par boutique, mais 2 boutiques peuvent avoir un code "SUMMER"
            $table->unique(['shop_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
