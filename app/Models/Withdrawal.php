<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = ['shop_id', 'amount', 'status', 'payment_method', 'details', 'admin_notes'];

    protected $casts = [
        'details' => 'array',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
