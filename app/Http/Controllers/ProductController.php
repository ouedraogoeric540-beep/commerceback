<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Contracts\StorageServiceInterface;

class ProductController extends Controller
{
    protected StorageServiceInterface $storage;

    public function __construct(StorageServiceInterface $storage)
    {
        $this->storage = $storage;
    }

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

            $product = Product::create([
                'shop_id' => $shop->id,
                'product_type' => $request->product_type,
                'title' => $request->title,
                'slug' => $slug,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
                'attributes' => $request->input('attributes') ? json_decode($request->input('attributes'), true) : null,
                'is_active' => true,
            ]);

            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                $filename = Str::random(10) . '.' . $file->getClientOriginalExtension();
                $coverPath = "products/{$product->id}/covers/{$filename}";
                $this->storage->upload('product-images', $coverPath, $file);
                $product->cover_image = $coverPath;
            }

            if ($request->hasFile('digital_file')) {
                $file = $request->file('digital_file');
                $filename = Str::random(10) . '.' . $file->getClientOriginalExtension();
                $digitalFilePath = "products/{$product->id}/files/{$filename}";
                $this->storage->upload('digital-products', $digitalFilePath, $file);
                $product->digital_file = $digitalFilePath;
            }

            if ($coverPath || $digitalFilePath) {
                $product->save();
            }

            DB::commit();

            return response()->json([
                'message' => __('api.produit_ajout_avec_succ_s'),
                'product' => $product
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($coverPath) {
                $this->storage->delete('product-images', $coverPath);
            }
            if ($digitalFilePath) {
                $this->storage->delete('digital-products', $digitalFilePath);
            }

            return response()->json(['message' => __('api.erreur_lors_de_la_cr_ation_du_')], 500);
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
            'message' => __('api.ordre_des_produits_mis_jour')
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
                $file = $request->file('cover_image');
                $filename = Str::random(10) . '.' . $file->getClientOriginalExtension();
                $newCoverPath = "products/{$product->id}/covers/{$filename}";
                
                $this->storage->upload('product-images', $newCoverPath, $file);
                
                if ($product->cover_image) {
                    $this->storage->delete('product-images', $product->cover_image);
                }
                $product->cover_image = $newCoverPath;
            }

            if ($request->hasFile('digital_file')) {
                $file = $request->file('digital_file');
                $filename = Str::random(10) . '.' . $file->getClientOriginalExtension();
                $newDigitalFilePath = "products/{$product->id}/files/{$filename}";
                
                $this->storage->upload('digital-products', $newDigitalFilePath, $file);
                
                if ($product->digital_file) {
                    $this->storage->delete('digital-products', $product->digital_file);
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
                'message' => __('api.produit_mis_jour_avec_succ_s'),
                'product' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($newCoverPath) {
                $this->storage->delete('product-images', $newCoverPath);
            }
            if ($newDigitalFilePath) {
                $this->storage->delete('digital-products', $newDigitalFilePath);
            }

            return response()->json(['message' => __('api.erreur_lors_de_la_mise_jour_du')], 500);
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
                $this->storage->delete('product-images', $coverImage);
            }

            if ($digitalFile) {
                $this->storage->delete('digital-products', $digitalFile);
            }

            DB::commit();

            return response()->json([
                'message' => __('api.produit_supprim_avec_succ_s')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => __('api.error_deleting')], 500);
        }
    }
}
