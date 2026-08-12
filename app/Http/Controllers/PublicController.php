<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Product;
use App\Models\GlobalCategory;
use Illuminate\Support\Facades\Cache;

class PublicController extends Controller
{
    public function getShop($slug)
    {
        $shop = Shop::where('slug', $slug)
                    ->where('status', 'approved')
                    ->first();
                    
        if (!$shop) {
            return response()->json(['message' => __('api.boutique_introuvable_ou_non_ap')], 404);
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
            return response()->json(['message' => __('api.produit_introuvable')], 404);
        }

        return response()->json([
            'product' => $product
        ]);
    }

    public function catalog(Request $request)
    {
        $query = Product::with(['shop:id,name,slug,logo', 'globalCategory:id,name'])
            ->where('is_active', true)
            ->whereHas('shop', function ($q) {
                $q->where('status', 'approved');
            });

        // Recherche par mot-clé
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtre par type (legacy)
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('product_type', $request->type);
        }

        // Filtre par catégorie globale
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('global_category_id', $request->category_id);
        }

        // Filtre par prix
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Tri
        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->latest(),
        };

        // Cache key based on request parameters
        $cacheKey = 'catalog_' . md5(json_encode($request->all()));

        $response = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query) {
            // Pagination
            $products = $query->paginate(12);

            // Catégories pour les filtres
            $categories = GlobalCategory::where('is_active', true)
                ->whereNull('parent_id')
                ->withCount(['products' => fn($q) => $q->where('is_active', true)])
                ->get();

            return [
                'products'   => $products,
                'categories' => $categories,
            ];
        });

        return response()->json($response);
    }
}
