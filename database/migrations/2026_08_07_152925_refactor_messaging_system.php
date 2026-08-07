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
        // 1. Ajouter les colonnes à `messages` (déjà exécuté)
        /*Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('conversation_id');
            $table->unsignedBigInteger('product_id')->nullable()->after('order_id');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });*/

        // 2. Migration des données
        $conversations = DB::table('conversations')->get();
        foreach ($conversations as $conv) {
            if ($conv->order_id || $conv->product_id) {
                DB::table('messages')
                    ->where('conversation_id', $conv->id)
                    ->update([
                        'order_id' => $conv->order_id,
                        'product_id' => $conv->product_id,
                    ]);
            }
        }

        // 3. Fusion des conversations en double
        $grouped = DB::table('conversations')
            ->select('buyer_id', 'shop_id', DB::raw('MIN(id) as primary_id'))
            ->groupBy('buyer_id', 'shop_id')
            ->get();

        foreach ($grouped as $group) {
            $duplicates = DB::table('conversations')
                ->where('buyer_id', $group->buyer_id)
                ->where('shop_id', $group->shop_id)
                ->where('id', '!=', $group->primary_id)
                ->pluck('id');

            if ($duplicates->isNotEmpty()) {
                // Réaffecter les messages
                DB::table('messages')
                    ->whereIn('conversation_id', $duplicates)
                    ->update(['conversation_id' => $group->primary_id]);
                
                // Supprimer les conversations en double
                DB::table('conversations')->whereIn('id', $duplicates)->delete();
            }
        }

        // 4. Modifier la structure de `conversations`
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_buyer_id_shop_id_order_id_unique');
            $table->dropForeign(['order_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['order_id', 'product_id', 'subject']);
            
            // Ajouter la contrainte d'unicité
            $table->unique(['buyer_id', 'shop_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['buyer_id', 'shop_id']);
            $table->unsignedBigInteger('order_id')->nullable()->after('shop_id');
            $table->unsignedBigInteger('product_id')->nullable()->after('order_id');
            $table->string('subject')->nullable()->after('product_id');

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['order_id', 'product_id']);
        });
    }
};
