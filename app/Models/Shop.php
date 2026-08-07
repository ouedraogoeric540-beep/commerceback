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
