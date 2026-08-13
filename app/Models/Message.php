<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'order_id',
        'product_id',
        'body',
        'is_system',
        'attachment_path',
        'attachment_name',
        'attachment_type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime:Y-m-d\TH:i:s.\0\0\0\0\0\0\Z',
    ];

    protected $appends = ['attachment_url'];

    protected $hidden = ['attachment_path'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getAttachmentUrlAttribute()
    {
        if (!$this->attachment_path) return null;
        if (str_starts_with($this->attachment_path, 'http')) return $this->attachment_path;
        return app(\App\Contracts\StorageServiceInterface::class)->temporaryUrl('user-files', $this->attachment_path, 3600);
    }
}
