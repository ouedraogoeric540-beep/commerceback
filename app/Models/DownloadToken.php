<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadToken extends Model
{
    protected $fillable = [
        'order_item_id',
        'token',
        'download_count',
        'max_downloads',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }



    public function isValid(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        
        if ($this->max_downloads !== null && $this->download_count >= $this->max_downloads) {
            return false;
        }

        return true;
    }
}
