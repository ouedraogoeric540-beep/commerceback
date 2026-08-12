<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;

class FunnelController extends Controller
{
    /**
     * Get specific product and shop data for the dedicated sales funnel page.
     */
    public function getFunnelData($shopSlug, $productSlug)
    {
        $shop = Shop::where('slug', $shopSlug)
            ->where('status', 'approved')
            ->first();

        if (!$shop) {
            return response()->json(['message' => __('api.boutique_introuvable_ou_non_ap')], 404);
        }

        $product = Product::where('shop_id', $shop->id)
            ->where('slug', $productSlug)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json(['message' => __('api.produit_introuvable')], 404);
        }

        // Get some other top products of this shop as potential recommendations
        $recommendations = Product::where('shop_id', $shop->id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(3)
            ->get();

        return response()->json([
            'shop' => $shop,
            'product' => $product,
            'recommendations' => $recommendations
        ]);
    }
}
