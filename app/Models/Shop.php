<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'logo', 'cover', 'status', 'slogan', 'settings',
        'support_email', 'support_phone',
        'address', 'city', 'postal_code', 'country',
        'registration_number', 'vat_number'
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $appends = ['logo_url', 'cover_url'];

    public function getLogoUrlAttribute()
    {
        if (!$this->logo) return null;
        if (str_starts_with($this->logo, 'http')) return $this->logo;
        return app(\App\Contracts\StorageServiceInterface::class)->publicUrl('public-assets', $this->logo);
    }

    public function getCoverUrlAttribute()
    {
        if (!$this->cover) return null;
        if (str_starts_with($this->cover, 'http')) return $this->cover;
        return app(\App\Contracts\StorageServiceInterface::class)->publicUrl('public-assets', $this->cover);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kycDocuments()
    {
        return $this->hasMany(KycDocument::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function promoCodes()
    {
        return $this->hasMany(PromoCode::class);
    }


}
