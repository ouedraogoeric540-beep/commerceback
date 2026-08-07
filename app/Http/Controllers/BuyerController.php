<?php

namespace App\Http\Controllers;


use App\Models\Order;
use App\Models\DownloadToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuyerController extends Controller
{

    /**
     * Get buyer's digital downloads.
     */
    public function myDownloads()
    {
        $buyerId = Auth::id();

        $tokens = DownloadToken::with(['orderItem.product.shop:id,name,logo,slug'])
            ->where('buyer_id', $buyerId)
            ->latest()
            ->get()
            ->map(function ($token) {
                return [
                    'id'           => $token->id,
                    'token'        => $token->token,
                    'product'      => $token->orderItem?->product,
                    'expires_at'   => $token->expires_at,
                    'download_count' => $token->download_count,
                    'max_downloads'  => $token->max_downloads,
                    'created_at'   => $token->created_at,
                    'download_url' => url('/api/downloads/' . $token->token),
                ];
            });

        return response()->json(['downloads' => $tokens]);
    }
}
