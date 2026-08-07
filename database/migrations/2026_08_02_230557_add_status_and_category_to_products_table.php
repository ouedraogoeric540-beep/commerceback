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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('global_category_id')->nullable()->constrained('global_categories')->nullOnDelete();
            $table->enum('status', ['active', 'suspended'])->default('active')->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['global_category_id']);
            $table->dropColumn(['global_category_id', 'status']);
        });
    }
};
