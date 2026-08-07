<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\NewOrderNotification;
use App\Services\DownloadService;

class PaymentService
{
    protected DownloadService $downloadService;

    public function __construct(DownloadService $downloadService)
    {
        $this->downloadService = $downloadService;
    }

    /**
     * Confirms the payment for an order and triggers the necessary side effects.
     * 
     * @param Order $order
     * @param string $transactionId
     * @param string $paymentMethod
     * @return bool
     */
    public function confirmPayment(Order $order, string $transactionId, string $paymentMethod = 'simulated'): bool
    {
        if ($order->status !== 'pending') {
            return false;
        }

        DB::transaction(function () use ($order, $transactionId, $paymentMethod) {
            // Lock order for update
            $order = Order::where('id', $order->id)->lockForUpdate()->first();
            
            $order->status = 'paid';
            $order->payment_id = $transactionId;
            $order->payment_method = $paymentMethod;
            $order->save();

            // Incrémentation du code promo
            if ($order->promo_code) {
                $shopIds = $order->items->pluck('shop_id')->unique();
                $promo = \App\Models\PromoCode::whereIn('shop_id', $shopIds)
                    ->where('code', $order->promo_code)
                    ->lockForUpdate()
                    ->first();
                if ($promo) {
                    $promo->increment('used_count');
                }
            }

            // Génération des tokens de téléchargement pour les produits numériques
            // et mise à jour du statut des items
            foreach ($order->items as $item) {
                // Update item status
                $item->status = 'paid';
                $item->save();

                if ($item->product && !in_array($item->product->product_type, ['physical_clothing', 'physical_item'])) {
                    $this->downloadService->generateTokenFor($item);
                }
            }

            // Notify buyer
            if ($order->user_id) {
                $user = User::find($order->user_id);
                if ($user) {
                    $user->notify(new OrderPlacedNotification($order));
                    \App\Services\NotificationService::send($user, 'Commande payée !', "Votre commande #{$order->id} a bien été enregistrée et payée.", 'order_paid', ['order_id' => $order->id]);
                }
            }
            
            // Notify sellers
            $shopsToNotify = [];
            foreach ($order->items as $item) {
                $shopId = $item->shop_id;
                if (!isset($shopsToNotify[$shopId])) {
                    $shopsToNotify[$shopId] = true;
                    $shop = \App\Models\Shop::with('user')->find($shopId);
                    if ($shop && $shop->user) {
                        $shop->user->notify(new NewOrderNotification($order, $shop));
                        \App\Services\NotificationService::send($shop->user, 'Nouvelle commande !', "Vous avez reçu une nouvelle commande #{$order->id} pour la boutique {$shop->name}.", 'new_order', ['order_id' => $order->id]);
                    }
                }
            }

            // Credit seller wallets and platform commission
            $financeService = resolve(\App\Services\FinanceService::class);
            $financeService->creditSellerForOrder($order);
        });

        return true;
    }
}
