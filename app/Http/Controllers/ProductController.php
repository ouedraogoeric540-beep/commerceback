<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => __('api.shop_not_found')], 404);
        }

        $query = $shop->products()->with('globalCategory')->latest();
        
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $products = $query->paginate(15);
        return response()->json(['products' => $products]);
    }

    public function store(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => __('api.shop_not_found')], 404);
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
                    $digitalFileRule .= '|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml';
                    break;
                case 'ebook':
                    $digitalFileRule .= '|mimetypes:application/pdf,application/epub+zip';
                    break;
                case 'software':
                    $digitalFileRule .= '|mimetypes:application/zip,application/x-rar-compressed,application/x-7z-compressed,application/gzip,application/x-tar';
                    break;
                case 'course':
                    $digitalFileRule .= '|mimetypes:video/mp4,video/x-msvideo,video/quicktime,audio/mpeg,audio/wav';
                    break;
                case 'file':
                default:
                    $digitalFileRule .= '|mimetypes:application/zip,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain';
                    break;
            }
            $rules['digital_file'] = $digitalFileRule;
        }

        $request->validate($rules, [
            'digital_file.mimetypes' => 'Le format du fichier uploadé est invalide ou non sécurisé pour ce type de produit.',
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
        $digitalFilePath = null;

        try {
            DB::beginTransaction();

            if ($request->hasFile('cover_image')) {
                $coverPath = $request->file('cover_image')->store('products/covers', 'public');
            }

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

            DB::commit();

            return response()->json([
                'message' => 'Produit ajouté avec succès.',
                'product' => $product
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Nettoyage des fichiers temporaires
            if ($coverPath) {
                Storage::disk('public')->delete($coverPath);
                Log::info("Fichier orphelin supprimé suite à erreur de création (cover): {$coverPath}");
            }
            if ($digitalFilePath) {
                Storage::delete($digitalFilePath);
                Log::info("Fichier orphelin supprimé suite à erreur de création (digital): {$digitalFilePath}");
            }

            return response()->json(['message' => 'Erreur lors de la création du produit.'], 500);
        }
    }

    /**
     * Réorganiser les produits
     */
    public function reorderProducts(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json(['message' => __('api.shop_not_found')], 404);
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
            return response()->json(['message' => __('api.shop_not_found')], 404);
        }

        $product = Product::where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$product) {
            return response()->json(['message' => __('api.product_not_found')], 404);
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
                    $digitalFileRule .= '|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml';
                    break;
                case 'ebook':
                    $digitalFileRule .= '|mimetypes:application/pdf,application/epub+zip';
                    break;
                case 'software':
                    $digitalFileRule .= '|mimetypes:application/zip,application/x-rar-compressed,application/x-7z-compressed,application/gzip,application/x-tar';
                    break;
                case 'course':
                    $digitalFileRule .= '|mimetypes:video/mp4,video/x-msvideo,video/quicktime,audio/mpeg,audio/wav';
                    break;
                case 'file':
                default:
                    $digitalFileRule .= '|mimetypes:application/zip,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain';
                    break;
            }
            $rules['digital_file'] = $digitalFileRule;
        }

        $request->validate($rules, [
            'digital_file.mimetypes' => 'Le format du fichier uploadé est invalide ou non sécurisé pour ce type de produit.',
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

        $newCoverPath = null;
        $newDigitalFilePath = null;

        try {
            DB::beginTransaction();

            if ($request->hasFile('cover_image')) {
                $newCoverPath = $request->file('cover_image')->store('products/covers', 'public');
                if ($product->cover_image) {
                    Storage::disk('public')->delete($product->cover_image);
                    Log::info("Ancienne image de couverture supprimée (mise à jour) : {$product->cover_image}");
                }
                $product->cover_image = $newCoverPath;
            }

            if ($request->hasFile('digital_file')) {
                $newDigitalFilePath = $request->file('digital_file')->store('products/digital_files');
                if ($product->digital_file) {
                    Storage::delete($product->digital_file);
                    Log::info("Ancien fichier numérique supprimé (mise à jour) : {$product->digital_file}");
                }
                $product->digital_file = $newDigitalFilePath;
            }

            $product->title = $request->title;
            $product->product_type = $request->product_type;
            $product->description = $request->description;
            $product->price = $request->price;
            $product->stock = $request->stock;
            $product->attributes = $request->input('attributes') ? json_decode($request->input('attributes'), true) : null;
            
            $product->save();
            DB::commit();

            return response()->json([
                'message' => 'Produit mis à jour avec succès.',
                'product' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($newCoverPath) {
                Storage::disk('public')->delete($newCoverPath);
                Log::info("Image de couverture orpheline supprimée (rollback) : {$newCoverPath}");
            }
            if ($newDigitalFilePath) {
                Storage::delete($newDigitalFilePath);
                Log::info("Fichier numérique orphelin supprimé (rollback) : {$newDigitalFilePath}");
            }

            return response()->json(['message' => 'Erreur lors de la mise à jour du produit.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => __('api.shop_not_found')], 404);
        }

        $product = Product::where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$product) {
            return response()->json(['message' => __('api.product_not_found')], 404);
        }

        try {
            DB::beginTransaction();

            $coverImage = $product->cover_image;
            $digitalFile = $product->digital_file;

            $product->delete();

            if ($coverImage) {
                Storage::disk('public')->delete($coverImage);
                Log::info("Image de couverture supprimée (suppression produit) : {$coverImage}");
            }

            if ($digitalFile) {
                Storage::delete($digitalFile);
                Log::info("Fichier numérique supprimé (suppression produit) : {$digitalFile}");
            }

            DB::commit();

            return response()->json([
                'message' => 'Produit supprimé avec succès.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => __('api.error_deleting')], 500);
        }
    }
}
