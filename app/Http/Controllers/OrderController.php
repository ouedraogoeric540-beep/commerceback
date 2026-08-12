<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PromoCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\OutOfStockNotification;
use App\Services\OrderStateMachineService;

class OrderController extends Controller
{
    protected OrderStateMachineService $stateMachine;

    public function __construct(OrderStateMachineService $stateMachine)
    {
        $this->stateMachine = $stateMachine;
    }
    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'cart_items' => 'required|array',
            'cart_items.*.id' => 'required|exists:products,id',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'promo_code' => 'nullable|array',
            'promo_code.code' => 'required_with:promo_code|string',
            'promo_code.shop_id' => 'required_with:promo_code|integer|exists:shops,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            $itemsData = [];

            // Verification des stocks et calcul du total
            foreach ($request->cart_items as $item) {
                $product = Product::with('shop')->lockForUpdate()->find($item['id']);

                if (!$product || !$product->is_active || $product->shop->status !== 'approved') {
                    throw new \Exception("Le produit '{$item['title']}' n'est plus disponible.");
                }

                if ($product->stock !== null && $product->stock < $item['quantity']) {
                    throw new \Exception("Stock insuffisant pour le produit '{$product->title}'.");
                }

                $totalAmount += $product->price * $item['quantity'];

                $itemsData[] = [
                    'product_id' => $product->id,
                    'shop_id' => $product->shop_id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ];

                // Déduction du stock
                if ($product->stock !== null) {
                    $product->stock -= $item['quantity'];
                    $product->save();
                    
                    if ($product->stock <= 0 && $product->shop && $product->shop->user) {
                        $product->shop->user->notify(new OutOfStockNotification($product));
                    }
                }
            }

            // Gestion du code promo
            $discountAmount = 0;
            $appliedPromo = null;

            if ($request->has('promo_code') && $request->promo_code) {
                $code = strtoupper(trim($request->promo_code['code']));
                $shopId = $request->promo_code['shop_id'];

                $promo = PromoCode::where('shop_id', $shopId)->where('code', $code)->lockForUpdate()->first();

                if ($promo && $promo->is_active && ($promo->max_uses === null || $promo->used_count < $promo->max_uses)) {
                    // Calculer le sous-total de la boutique concernée
                    $shopSubtotal = 0;
                    foreach ($itemsData as $data) {
                        if ($data['shop_id'] == $shopId) {
                            $shopSubtotal += ($data['price'] * $data['quantity']);
                        }
                    }

                    if ($promo->min_amount === null || $shopSubtotal >= $promo->min_amount) {
                        if ($promo->type === 'percentage') {
                            $discountAmount = ($shopSubtotal * $promo->value) / 100;
                        } else {
                            $discountAmount = $promo->value;
                        }

                        // On s'assure que la remise ne dépasse pas le sous-total de la boutique
                        if ($discountAmount > $shopSubtotal) {
                            $discountAmount = $shopSubtotal;
                        }

                        $totalAmount -= $discountAmount;
                        $appliedPromo = $promo;

                        // Répartir la réduction sur les articles de cette boutique
                        if ($discountAmount > 0 && $shopSubtotal > 0) {
                            foreach ($itemsData as &$data) {
                                if ($data['shop_id'] == $shopId) {
                                    $itemTotal = $data['price'] * $data['quantity'];
                                    $itemProportion = $itemTotal / $shopSubtotal;
                                    $itemDiscount = $discountAmount * $itemProportion;
                                    
                                    // Ajuster le prix unitaire
                                    $data['price'] = $data['price'] - ($itemDiscount / $data['quantity']);
                                }
                            }
                            unset($data);
                        }
                    }
                }
            }

            // Création de la commande
            $order = Order::create([
                'user_id' => auth()->id() ?? null,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'total_amount' => $totalAmount,
                'promo_code' => $appliedPromo ? $appliedPromo->code : null,
                'status' => 'completed',
            ]);

            // Création des OrderItems
            foreach ($itemsData as $data) {
                $order->items()->create($data);
            }

            if ($appliedPromo) {
                $appliedPromo->increment('used_count');
            }
            
            // Create download tokens
            foreach ($order->items as $item) {
                if ($item->product && $item->product->digital_file) {
                    \App\Models\DownloadToken::createForOrder($item);
                }
            }

            DB::commit();

            return response()->json([
                'message' => __('api.commande_effectu_e_avec_succ_s'),
                'order_id' => $order->id
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getBuyerStats(Request $request)
    {
        $user = $request->user();
        $orders = Order::where('email', $user->email)
            ->with(['items.product.shop'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalOrders = $orders->count();
        $lastOrder = $orders->first();
        $recentOrders = $orders->take(5);

        // Nouveaux KPIs Acheteur
        $totalItemsBought = 0;
        $shopCounts = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $totalItemsBought += $item->quantity;
                if ($item->product && $item->product->shop) {
                    $shopName = $item->product->shop->name;
                    $shopCounts[$shopName] = ($shopCounts[$shopName] ?? 0) + $item->quantity;
                }
            }
        }
        
        arsort($shopCounts);
        $favoriteShop = !empty($shopCounts) ? array_key_first($shopCounts) : null;

        // Préparation des données pour le graphique (6 derniers mois)
        $chartData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            
            $monthOrders = $orders->whereBetween('created_at', [$monthStart, $monthEnd])->count();
            
            $chartData[] = [
                'name' => $date->translatedFormat('M'), // Jan, Fév, Mar...
                'commandes' => $monthOrders
            ];
        }

        return response()->json([
            'total_orders' => $totalOrders,
            'last_order_date' => $lastOrder ? $lastOrder->created_at : null,
            'recent_orders' => $recentOrders,
            'total_items_bought' => $totalItemsBought,
            'favorite_shop' => $favoriteShop,
            'chart_data' => $chartData
        ]);
    }

    public function getSellerStats(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json(['message' => __('api.aucune_boutique_trouv_e')], 404);
        }

        $orderItems = OrderItem::where('shop_id', $shop->id)
            ->with(['order', 'product'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalSales = $orderItems->sum('quantity'); // Nombre de produits vendus (au lieu de count)
        
        // 2. Produits en ligne (actifs)
        $activeProductsCount = \App\Models\Product::where('shop_id', $shop->id)->where('is_active', 1)->count();

        // 3. Meilleur Produit
        $productSales = [];
        foreach ($orderItems as $item) {
            if ($item->product) {
                $productName = $item->product->title ?? $item->product->name;
                $productSales[$productName] = ($productSales[$productName] ?? 0) + $item->quantity;
            }
        }
        arsort($productSales);
        $bestProduct = !empty($productSales) ? array_key_first($productSales) : null;

        // 4. Produits en rupture de stock
        $outOfStockCount = \App\Models\Product::where('shop_id', $shop->id)->whereNotNull('stock')->where('stock', '<=', 0)->count();

        // 5. Codes Promo Actifs
        $activePromoCodesCount = \App\Models\PromoCode::where('shop_id', $shop->id)->where('is_active', 1)->count();

        // 6. Utilisation totale des codes promo
        $totalPromoUses = \App\Models\PromoCode::where('shop_id', $shop->id)->sum('used_count');

        // Préparation des données pour le graphique (6 derniers mois)
        $chartData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            
            $monthSales = $orderItems->whereBetween('created_at', [$monthStart, $monthEnd])->sum('quantity');
            
            $chartData[] = [
                'name' => $date->translatedFormat('M'),
                'ventes' => $monthSales
            ];
        }

        return response()->json([
            'total_sales' => $totalSales,
            'active_products_count' => $activeProductsCount,
            'best_product' => $bestProduct,
            'out_of_stock_count' => $outOfStockCount,
            'active_promo_codes_count' => $activePromoCodesCount,
            'total_promo_uses' => $totalPromoUses,
            'chart_data' => $chartData
        ]);
    }

    public function getBuyerOrders(Request $request)
    {
        $user = $request->user();

        $query = Order::where('email', $user->email)
            ->with(['items.product.shop', 'items.product'])
            ->orderBy('created_at', 'desc');

        $orders = $query->paginate(15);

        return response()->json([
            'orders' => $orders
        ]);
    }

    public function getSellerOrders(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json(['message' => __('api.aucune_boutique_trouv_e')], 404);
        }

        $query = OrderItem::where('shop_id', $shop->id)
            ->with(['order', 'product'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('order', function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('product', function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(15);

        return response()->json([
            'orders' => $orders
        ]);
    }

    public function downloadDigitalProduct(Request $request, $orderId, $productId)
    {
        $user = $request->user();

        // 1. Vérifier que la commande appartient à l'utilisateur
        $order = Order::where('id', $orderId)->where('email', $user->email)->first();
        if (!$order) {
            return response()->json(['message' => __('api.commande_introuvable_ou_non_au')], 403);
        }

        // 2. Vérifier que le produit est bien dans cette commande
        $orderItem = OrderItem::where('order_id', $orderId)->where('product_id', $productId)->first();
        if (!$orderItem) {
            return response()->json(['message' => __('api.product_not_in_order')], 403);
        }

        $product = $orderItem->product;

        // 3. Vérifier que c'est bien un produit numérique et qu'il possède un fichier
        if (in_array($product->product_type, ['physical_clothing', 'physical_item']) || !$product->digital_file) {
            return response()->json(['message' => __('api.aucun_fichier_t_l_chargeable_p')], 404);
        }

        // 4. Vérifier que le fichier existe physiquement sur le disque (stockage local privé)
        if (!Storage::exists($product->digital_file)) {
            return response()->json(['message' => __('api.file_not_found')], 404);
        }

        // 5. Télécharger le fichier
        // On récupère l'extension originale du fichier
        $extension = pathinfo($product->digital_file, PATHINFO_EXTENSION);
        // Nom du fichier sécurisé (slug du produit + extension)
        $downloadName = $product->slug . '.' . $extension;

        return Storage::download($product->digital_file, $downloadName);
    }



    public function successDetails($id)
    {
        $order = Order::with(['items.product.shop'])->find($id);
        if (!$order) {
            return response()->json(['message' => __('api.order_not_found')], 404);
        }
        
        // Sécurité basique pour achat invité (limite à 24h)
        if ($order->created_at->diffInHours(now()) > 24) {
            return response()->json(['message' => __('api.lien_expir_pour_des_raisons_de')], 403);
        }

        $messages = [];
        $downloads = [];
        foreach ($order->items as $item) {
            if ($item->product && $item->product->shop) {
                $msg = $item->product->shop->settings['post_sale_message'] ?? null;
                if ($msg && !in_array($msg, $messages)) {
                    $messages[] = $msg;
                }
            }
            
            $tokenObj = \App\Models\DownloadToken::where('order_item_id', $item->id)->first();
            if ($tokenObj && $tokenObj->isValid()) {
                $downloads[] = [
                    'product_title' => $item->product->title ?? 'Produit numérique',
                    'url' => url('/api/downloads/' . $tokenObj->token),
                    'expires_at' => $tokenObj->expires_at
                ];
            }
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'email' => $order->email,
                'status' => $order->status,
            ],
            'messages' => $messages,
            'downloads' => $downloads
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user->shop) {
            return response()->json(['message' => __('api.non_autoris')], 403);
        }

        $orderItem = OrderItem::find($id);

        if (!$orderItem) {
            return response()->json(['message' => __('api.order_item_not_found')], 404);
        }

        if ($orderItem->shop_id !== $user->shop->id) {
            return response()->json(['message' => __('api.cet_article_nappartient_pas_a_votre_boutique')], 403);
        }

        try {
            // State Machine Validation
            $this->stateMachine->assertCanTransition($orderItem->status, $request->status);
            
            $orderItem->status = $request->status;
            $orderItem->save();

            // Notify via system message in chat
            if (in_array($request->status, ['completed', 'cancelled'])) {
                $statusText = $request->status === 'completed' ? 'marquée comme terminée' : 'annulée';
                \App\Models\Conversation::sendSystemMessage(
                    $orderItem->order->user_id,
                    $orderItem->shop_id,
                    "✅ La commande #{$orderItem->order->id} a été $statusText par le vendeur.",
                    $orderItem->order->id,
                    $orderItem->product_id
                );
            }

            return response()->json(['message' => __('api.statut_mis_jour_avec_succ_s'), 'status' => $orderItem->status]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
