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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('body')->nullable(); // Contenu du message
            $table->string('attachment_path')->nullable(); // Chemin du fichier joint
            $table->string('attachment_name')->nullable(); // Nom original du fichier
            $table->string('attachment_type')->nullable(); // image, pdf, etc.
            $table->timestamp('read_at')->nullable(); // Lu par le destinataire
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
