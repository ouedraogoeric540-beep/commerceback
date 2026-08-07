<?php

namespace App\Http\Controllers;

use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class HomepageController extends Controller
{
    /**
     * All public homepage data in one call.
     */
    public function index()
    {
        // CMS settings
        $cmsKeys = ['homepage_banner_title', 'homepage_banner_subtitle', 'homepage_cta_text', 'platform_name', 'social_facebook', 'social_instagram', 'social_twitter'];
        $cms = [];
        foreach ($cmsKeys as $key) {
            $cms[$key] = Setting::get($key, '');
        }

        // Categories with product count
        $categories = GlobalCategory::withCount(['products' => function ($q) {
            $q->where('is_active', true)->whereHas('shop', fn($s) => $s->where('status', 'approved'));
        }])
        ->where('is_active', true)
        ->whereNull('parent_id')
        ->orderByDesc('products_count')
        ->limit(8)
        ->get();

        // Newest products
        $newest = Product::with('shop:id,name,slug,logo', 'globalCategory:id,name')
            ->where('is_active', true)
            ->whereHas('shop', fn($q) => $q->where('status', 'approved'))
            ->latest()
            ->limit(8)
            ->get();

        // Best sellers (ordered by order_items count)
        $bestSellers = Product::with('shop:id,name,slug,logo', 'globalCategory:id,name')
            ->where('is_active', true)
            ->whereHas('shop', fn($q) => $q->where('status', 'approved'))
            ->inRandomOrder()
            ->limit(8)
            ->get();

        return response()->json([
            'cms' => $cms,
            'categories' => $categories,
            'newest' => $newest,
            'bestSellers' => $bestSellers,
        ]);
    }
}
