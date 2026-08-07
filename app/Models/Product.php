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

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function globalCategory()
    {
        return $this->belongsTo(GlobalCategory::class, 'global_category_id');
    }
}
