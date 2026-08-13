<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'shop_id', 'global_category_id', 'product_type', 'title', 'slug',
        'description', 'price', 'stock', 'cover_image', 'digital_file',
        'attributes', 'is_active', 'status', 'sort_order'
    ];

    protected $casts = [
        'attributes' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    protected $appends = ['cover_image_url'];

    public function getCoverImageUrlAttribute()
    {
        if (!$this->cover_image) return null;
        if (str_starts_with($this->cover_image, 'http')) return $this->cover_image;
        return app(\App\Contracts\StorageServiceInterface::class)->publicUrl('product-images', $this->cover_image);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function globalCategory()
    {
        return $this->belongsTo(GlobalCategory::class, 'global_category_id');
    }
}
