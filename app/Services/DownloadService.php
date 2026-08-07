<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\DownloadToken;
use Illuminate\Support\Str;

class DownloadService
{
    /**
     * Generate a secure download token for an order item.
     */
    public function generateTokenFor(OrderItem $item, int $maxDownloads = 5, int $daysValid = 7): DownloadToken
    {
        return DownloadToken::create([
            'order_item_id' => $item->id,
            'token' => Str::random(64),
            'max_downloads' => $maxDownloads,
            'expires_at' => now()->addDays($daysValid),
        ]);
    }

    /**
     * Record a download and increment the count.
     */
    public function recordDownload(DownloadToken $token, ?string $ip, ?string $userAgent): void
    {
        $token->increment('download_count');
        
        $token->logs()->create([
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ]);
    }
}
