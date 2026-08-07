<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Services\DisputeService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminDisputeController extends Controller
{
    protected $disputeService;

    public function __construct(DisputeService $disputeService)
    {
        $this->disputeService = $disputeService;
    }

    public function getDisputes(Request $request)
    {
        $status = $request->query('status');
        
        $query = Dispute::with(['order:id,total', 'buyer:id,name', 'shop:id,name'])->latest();
        
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    public function getDisputeDetails($id)
    {
        $dispute = Dispute::with([
            'order.items.product',
            'buyer:id,name,email',
            'shop:id,name,email',
            'messages.user:id,name'
        ])->findOrFail($id);

        return response()->json($dispute);
    }

    public function addMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $dispute = Dispute::findOrFail($id);
        
        $message = DisputeMessage::create([
            'dispute_id' => $dispute->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_admin' => true
        ]);

        $message->load('user:id,name');

        return response()->json($message);
    }

    public function resolveDispute(Request $request, $id)
    {
        $request->validate([
            'resolution' => 'required|in:buyer_won,seller_won,closed',
            'admin_notes' => 'nullable|string'
        ]);

        $dispute = Dispute::findOrFail($id);

        try {
            $dispute = $this->disputeService->resolveDispute(
                $dispute,
                $request->resolution,
                $request->admin_notes
            );

            // Notify buyer
            $order = $dispute->order;
            if ($order && $order->user_id) {
                $buyerUser = \App\Models\User::find($order->user_id);
                if ($buyerUser) {
                    \App\Services\NotificationService::send(
                        $buyerUser,
                        'Litige résolu',
                        "Le litige concernant votre commande #{$order->id} a été résolu. Résultat: " . $request->resolution,
                        'dispute_resolved',
                        ['dispute_id' => $dispute->id, 'resolution' => $request->resolution]
                    );
                }
            }

            // Notify shop owner (vendor)
            $shop = $order ? \App\Models\Shop::with('user')->find($order->items->first()?->shop_id) : null;
            if ($shop && $shop->user) {
                \App\Services\NotificationService::send(
                    $shop->user,
                    'Litige résolu',
                    "Le litige concernant la commande #{$order->id} de votre boutique a été résolu. Résultat: " . $request->resolution,
                    'dispute_resolved',
                    ['dispute_id' => $dispute->id, 'resolution' => $request->resolution]
                );
            }

            AuditLogService::log('dispute.resolved', $dispute, [
                'resolution' => $request->resolution,
                'order_id'   => $dispute->order_id,
            ]);

            return response()->json([
                'message' => 'Le litige a été tranché avec succès.',
                'dispute' => $dispute
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
