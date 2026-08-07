<?php

namespace App\Services;

use App\Models\GlobalCategory;
use App\Models\Product;
use Illuminate\Support\Str;

class AdminCatalogService
{
    /**
     * Create a new category
     */
    public function createCategory(array $data)
    {
        $data['slug'] = Str::slug($data['name']);
        
        // Ensure slug uniqueness
        $originalSlug = $data['slug'];
        $counter = 1;
        while (GlobalCategory::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter++;
        }

        return GlobalCategory::create($data);
    }

    /**
     * Update an existing category
     */
    public function updateCategory(GlobalCategory $category, array $data)
    {
        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = Str::slug($data['name']);
            // Ensure slug uniqueness
            $originalSlug = $data['slug'];
            $counter = 1;
            while (GlobalCategory::where('slug', $data['slug'])->where('id', '!=', $category->id)->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter++;
            }
        }

        $category->update($data);
        return $category;
    }

    /**
     * Toggle product suspension status
     */
    public function toggleProductSuspension(Product $product)
    {
        $newStatus = $product->status === 'active' ? 'suspended' : 'active';
        $product->update(['status' => $newStatus]);
        return $product;
    }

}
