<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Product;

class PublicController extends Controller
{
    public function getShop($slug)
    {
        $shop = Shop::where('slug', $slug)
                    ->where('status', 'approved')
                    ->first();
                    
        if (!$shop) {
            return response()->json(['message' => 'Boutique introuvable ou non approuvée.'], 404);
        }

        // Charger les produits actifs
        $products = $shop->products()->where('is_active', true)->latest()->get();

        return response()->json([
            'shop' => $shop,
            'products' => $products
        ]);
    }

    public function getProduct($id)
    {
        $product = Product::with('shop')->where('is_active', true)->find($id);

        if (!$product || $product->shop->status !== 'approved') {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json([
            'product' => $product
        ]);
    }
}
