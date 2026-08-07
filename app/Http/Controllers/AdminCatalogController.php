<?php

namespace App\Http\Controllers;

use App\Models\GlobalCategory;
use App\Models\Product;
use App\Services\AdminCatalogService;
use Illuminate\Http\Request;

class AdminCatalogController extends Controller
{
    protected $catalogService;

    public function __construct(AdminCatalogService $catalogService)
    {
        $this->catalogService = $catalogService;
    }

    // --- CATEGORIES ---

    public function getCategories(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }
        
        // Return tree structure
        $categories = GlobalCategory::whereNull('parent_id')
                        ->with('children')
                        ->get();
                        
        return response()->json(['categories' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:global_categories,id',
            'is_active' => 'boolean'
        ]);

        $category = $this->catalogService->createCategory($request->only(['name', 'parent_id', 'is_active']));
        
        return response()->json(['message' => 'Catégorie créée avec succès.', 'category' => $category], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $category = GlobalCategory::findOrFail($id);

        $request->validate([
            'name' => 'string|max:255',
            'parent_id' => 'nullable|exists:global_categories,id',
            'is_active' => 'boolean'
        ]);

        // Prevent self-parenting
        if ($request->parent_id == $id) {
            return response()->json(['message' => 'Une catégorie ne peut pas être son propre parent.'], 422);
        }

        $category = $this->catalogService->updateCategory($category, $request->only(['name', 'parent_id', 'is_active']));
        
        return response()->json(['message' => 'Catégorie mise à jour.', 'category' => $category]);
    }

    // --- PRODUCTS ---

    public function getProducts(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $query = Product::with(['shop', 'globalCategory']);

        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }



        $products = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json(['products' => $products]);
    }

    public function toggleProductSuspension(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $product = Product::findOrFail($id);
        
        try {
            $product = $this->catalogService->toggleProductSuspension($product);
            $msg = $product->status === 'suspended' ? 'Produit suspendu.' : 'Produit réactivé.';
            return response()->json(['message' => $msg, 'product' => $product->load(['shop', 'globalCategory'])]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la suspension.'], 500);
        }
    }


}

