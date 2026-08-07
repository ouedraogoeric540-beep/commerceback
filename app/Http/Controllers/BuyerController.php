<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\DownloadToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuyerController extends Controller
{
    /**
     * Open a dispute for a completed order.
     */
    public function openDispute(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'reason'   => 'required|string|max:50',
            'message'  => 'required|string|max:2000',
        ]);

        $buyerId = Auth::id();

        $order = Order::where('id', $request->order_id)
            ->where('buyer_id', $buyerId)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        if (!in_array($order->status, ['paid', 'completed', 'delivered'])) {
            return response()->json(['message' => 'Vous ne pouvez ouvrir un litige que sur une commande déjà payée.'], 400);
        }

        // Check no existing open dispute for this order
        $existingDispute = Dispute::where('order_id', $order->id)->whereIn('status', ['open', 'under_review'])->first();
        if ($existingDispute) {
            return response()->json(['message' => 'Un litige est déjà ouvert pour cette commande.'], 400);
        }

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'reason'   => $request->reason,
            'status'   => 'open',
        ]);

        // Add the opening message from the buyer
        $dispute->messages()->create([
            'user_id'  => $buyerId,
            'message'  => $request->message,
            'is_admin' => false,
        ]);

        // Notify seller
        $shop = \App\Models\Shop::with('user')->find($order->items->first()?->shop_id);
        if ($shop && $shop->user) {
            \App\Services\NotificationService::send(
                $shop->user,
                'Nouveau litige ouvert',
                "Un litige a été ouvert par l'acheteur pour la commande #{$order->id}.",
                'dispute_opened',
                ['dispute_id' => $dispute->id, 'order_id' => $order->id]
            );
        }

        return response()->json([
            'message' => 'Votre litige a été ouvert avec succès. Nous vous contacterons dans les plus brefs délais.',
            'dispute' => $dispute,
        ]);
    }

    /**
     * List buyer's disputes.
     */
    public function myDisputes()
    {
        $buyerId = Auth::id();

        $disputes = Dispute::with(['order'])
            ->whereHas('order', fn($q) => $q->where('buyer_id', $buyerId))
            ->latest()
            ->get();

        return response()->json(['disputes' => $disputes]);
    }

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
