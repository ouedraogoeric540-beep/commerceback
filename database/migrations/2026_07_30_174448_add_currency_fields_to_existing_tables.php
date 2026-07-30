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
            $table->string('default_currency', 3)->default('XOF')->after('description');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('XOF')->after('price');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('original_currency', 3)->default('XOF')->after('total_amount');
            $table->string('display_currency', 3)->default('XOF')->after('original_currency');
            $table->decimal('exchange_rate', 15, 6)->default(1.0)->after('display_currency');
            
            $table->decimal('original_subtotal', 15, 6)->nullable();
            $table->decimal('original_shipping', 15, 6)->nullable();
            $table->decimal('original_tax', 15, 6)->nullable();
            $table->decimal('original_total', 15, 6)->nullable();

            $table->decimal('converted_subtotal', 15, 6)->nullable();
            $table->decimal('converted_shipping', 15, 6)->nullable();
            $table->decimal('converted_tax', 15, 6)->nullable();
            $table->decimal('converted_total', 15, 6)->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('original_currency', 3)->default('XOF')->after('price');
            $table->string('display_currency', 3)->default('XOF')->after('original_currency');
            $table->decimal('exchange_rate', 15, 6)->default(1.0)->after('display_currency');
            
            $table->decimal('original_price', 15, 6)->nullable();
            $table->decimal('converted_price', 15, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['original_currency', 'display_currency', 'exchange_rate', 'original_price', 'converted_price']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'original_currency', 'display_currency', 'exchange_rate',
                'original_subtotal', 'original_shipping', 'original_tax', 'original_total',
                'converted_subtotal', 'converted_shipping', 'converted_tax', 'converted_total'
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('currency_code');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('default_currency');
        });
    }
};
