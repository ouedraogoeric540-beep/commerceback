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
        Schema::table('shops', function (Blueprint $table) {
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            
            $table->string('registration_number')->nullable();
            $table->string('vat_number')->nullable();
            
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_holder')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'support_email', 'support_phone',
                'address', 'city', 'postal_code', 'country',
                'registration_number', 'vat_number',
                'iban', 'bic', 'bank_name', 'bank_account_holder'
            ]);
        });
    }
};
