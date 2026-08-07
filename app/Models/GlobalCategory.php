<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'slug', 'parent_id', 'is_active'])]
class GlobalCategory extends Model
{
    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(GlobalCategory::class, 'parent_id');
    }

    /**
     * Get the sub-categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(GlobalCategory::class, 'parent_id');
    }

    /**
     * Get the products for the category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'global_category_id');
    }
}
