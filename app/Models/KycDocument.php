<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycDocument extends Model
{
    protected $fillable = [
        'shop_id', 'type', 'document_recto', 'document_verso', 'status', 'rejection_reason'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
