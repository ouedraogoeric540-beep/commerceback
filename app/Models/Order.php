<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'first_name',
        'last_name',
        'total_amount',
        'promo_code',
        'status',
        'payment_method',
        'payment_id',
        'billing_address',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
