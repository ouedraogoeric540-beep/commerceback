<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => 'Boutique introuvable.'], 404);
        }

        $products = $shop->products()->latest()->get();
        return response()->json(['products' => $products]);
    }

    public function store(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => 'Boutique introuvable.'], 404);
        }

        $rules = [
            'title' => 'required|string|max:255',
            'product_type' => 'required|string|in:file,ebook,software,course,image,physical_clothing,physical_item',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'cover_image' => 'nullable|image|max:5120',
            'attributes' => 'nullable|json'
        ];

        // Validation conditionnelle pour digital_file
        $isPhysical = in_array($request->product_type, ['physical_clothing', 'physical_item']);
        
        if (!$isPhysical) {
            $digitalFileRule = 'required|file|max:512000'; // 500MB max

            switch ($request->product_type) {
                case 'image':
                    $digitalFileRule .= '|mimes:jpeg,png,jpg,gif,svg,webp';
                    break;
                case 'ebook':
                    $digitalFileRule .= '|mimes:pdf';
                    break;
                case 'software':
                    $digitalFileRule .= '|mimes:zip,rar,7z,tar,gz';
                    break;
                case 'course':
                    $digitalFileRule .= '|mimes:mp4,avi,mov,wmv,mp3,wav';
                    break;
                case 'file':
                default:
                    $digitalFileRule .= '|mimes:zip,rar,7z,pdf,doc,docx,xls,xlsx,txt';
                    break;
            }
            $rules['digital_file'] = $digitalFileRule;
        }

        $request->validate($rules, [
            'digital_file.mimes' => 'Le format du fichier uploader est invalide pour ce type de produit.',
            'product_type.in' => 'Le type de produit est invalide.'
        ]);

        $slug = Str::slug($request->title);
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('shop_id', $shop->id)->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('products/covers', 'public');
        }

        $digitalFilePath = null;
        if ($request->hasFile('digital_file')) {
            // Stockage PRIVÉ pour le fichier numérique
            $digitalFilePath = $request->file('digital_file')->store('products/digital_files');
        }

        $product = Product::create([
            'shop_id' => $shop->id,
            'product_type' => $request->product_type,
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'cover_image' => $coverPath,
            'digital_file' => $digitalFilePath,
            'attributes' => $request->input('attributes') ? json_decode($request->input('attributes'), true) : null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Produit ajouté avec succès.',
            'product' => $product
        ], 201);
    }

    /**
     * Réorganiser les produits
     */
    public function reorderProducts(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json(['message' => 'Boutique introuvable.'], 404);
        }

        $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:products,id'
        ]);

        foreach ($request->ordered_ids as $index => $productId) {
            \App\Models\Product::where('id', $productId)
                   ->where('shop_id', $shop->id)
                   ->update(['sort_order' => $index]);
        }

        return response()->json([
            'message' => 'Ordre des produits mis à jour.'
        ]);
    }

    public function update(Request $request, $id)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => 'Boutique introuvable.'], 404);
        }

        $product = Product::where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $rules = [
            'title' => 'required|string|max:255',
            'product_type' => 'required|string|in:file,ebook,software,course,image,physical_clothing,physical_item',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'cover_image' => 'nullable|image|max:5120',
            'attributes' => 'nullable|json'
        ];

        $isPhysical = in_array($request->product_type, ['physical_clothing', 'physical_item']);
        
        if (!$isPhysical && $request->hasFile('digital_file')) {
            $digitalFileRule = 'file|max:512000'; // 500MB max

            switch ($request->product_type) {
                case 'image':
                    $digitalFileRule .= '|mimes:jpeg,png,jpg,gif,svg,webp';
                    break;
                case 'ebook':
                    $digitalFileRule .= '|mimes:pdf';
                    break;
                case 'software':
                    $digitalFileRule .= '|mimes:zip,rar,7z,tar,gz';
                    break;
                case 'course':
                    $digitalFileRule .= '|mimes:mp4,avi,mov,wmv,mp3,wav';
                    break;
                case 'file':
                default:
                    $digitalFileRule .= '|mimes:zip,rar,7z,pdf,doc,docx,xls,xlsx,txt';
                    break;
            }
            $rules['digital_file'] = $digitalFileRule;
        }

        $request->validate($rules, [
            'digital_file.mimes' => 'Le format du fichier uploader est invalide pour ce type de produit.',
            'product_type.in' => 'Le type de produit est invalide.'
        ]);

        $slug = Str::slug($request->title);
        if ($slug !== $product->slug) {
            $originalSlug = $slug;
            $counter = 1;
            while (Product::where('shop_id', $shop->id)->where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $product->slug = $slug;
        }

        if ($request->hasFile('cover_image')) {
            if ($product->cover_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($product->cover_image);
            }
            $product->cover_image = $request->file('cover_image')->store('products/covers', 'public');
        }

        if ($request->hasFile('digital_file')) {
            if ($product->digital_file) {
                \Illuminate\Support\Facades\Storage::delete($product->digital_file);
            }
            $product->digital_file = $request->file('digital_file')->store('products/digital_files');
        }

        $product->title = $request->title;
        $product->product_type = $request->product_type;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->stock = $request->stock;
        $product->attributes = $request->input('attributes') ? json_decode($request->input('attributes'), true) : null;
        
        $product->save();

        return response()->json([
            'message' => 'Produit mis à jour avec succès.',
            'product' => $product
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => 'Boutique introuvable.'], 404);
        }

        $product = Product::where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        if ($product->cover_image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->cover_image);
        }

        if ($product->digital_file) {
            \Illuminate\Support\Facades\Storage::delete($product->digital_file);
        }

        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès.'
        ]);
    }
}
