<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'buyer_id',
        'shop_id',
        'order_id',
        'product_id',
        'subject',
        'status',
        'buyer_read_at',
        'seller_read_at',
        'last_message_at',
    ];

    protected $casts = [
        'buyer_read_at'    => 'datetime',
        'seller_read_at'   => 'datetime',
        'last_message_at'  => 'datetime',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
