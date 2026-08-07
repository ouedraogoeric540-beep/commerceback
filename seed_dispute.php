<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Shop;
use App\Models\Order;
use App\Models\Dispute;
use App\Models\DisputeMessage;

$user = User::first();
$shop = Shop::first();
$order = Order::first();

if (!$user || !$shop || !$order) {
    echo "Missing data to create a dispute.\n";
    exit;
}

$dispute = Dispute::create([
    'order_id' => $order->id,
    'buyer_id' => $user->id,
    'shop_id' => $shop->id,
    'reason' => 'Produit non reçu',
    'description' => "Je n'ai toujours pas reçu mon produit numérique après 48h.",
    'status' => 'open'
]);

DisputeMessage::create([
    'dispute_id' => $dispute->id,
    'user_id' => $user->id,
    'message' => "Le vendeur ne répond pas à mes messages. Je demande un remboursement.",
    'is_admin' => false
]);

DisputeMessage::create([
    'dispute_id' => $dispute->id,
    'user_id' => $shop->user_id ?? $user->id, 
    'message' => "Bonjour, il y a eu un souci avec le serveur. Je vous l'envoie tout de suite.",
    'is_admin' => false
]);

echo "Dispute seeded successfully!\n";
