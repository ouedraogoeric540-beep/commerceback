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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            
            $table->string('product_type')->default('file'); // file, ebook, software, course, etc.
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            
            $table->string('cover_image')->nullable();
            $table->string('digital_file')->nullable(); // Le fichier privé
            
            $table->json('attributes')->nullable(); // Champs dynamiques
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['shop_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
