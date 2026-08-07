<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'buyer_id',
        'shop_id',
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



    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Helper to send a system message in the chat
     */
    public static function sendSystemMessage($buyer_id, $shop_id, $body, $order_id = null, $product_id = null)
    {
        $conversation = self::firstOrCreate(
            ['buyer_id' => $buyer_id, 'shop_id' => $shop_id]
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => null, // Null means system
            'body'            => $body,
            'is_system'       => true,
            'order_id'        => $order_id,
            'product_id'      => $product_id,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return $message;
    }
}
