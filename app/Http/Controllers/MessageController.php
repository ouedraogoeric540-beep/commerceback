<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Shop;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     * Works for both buyers and sellers.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Un utilisateur peut forcer la vue acheteur même s'il a une boutique
        $forceBuyerView = $request->query('role') === 'buyer';

        if ($user->shop && !$forceBuyerView) {
            // Seller: conversations for their shop
            $conversations = Conversation::where('shop_id', $user->shop->id)
                ->with(['buyer:id,name,avatar', 'lastMessage.order:id,total_amount', 'lastMessage.product:id,name'])
                ->orderByDesc('last_message_at')
                ->get()
                ->map(function ($conv) use ($user) {
                    return array_merge($conv->toArray(), [
                        'unread_count' => $conv->messages()
                            ->whereNull('read_at')
                            ->where('sender_id', '!=', $user->id)
                            ->count(),
                    ]);
                })->values();
        } else {
            // Buyer: their own conversations (as a buyer)
            $conversations = Conversation::where('buyer_id', $user->id)
                ->with(['shop:id,name,logo,slug', 'lastMessage.order:id,total_amount', 'lastMessage.product:id,name'])
                ->orderByDesc('last_message_at')
                ->get()
                ->map(function ($conv) use ($user) {
                    return array_merge($conv->toArray(), [
                        'unread_count' => $conv->messages()
                            ->whereNull('read_at')
                            ->where('sender_id', '!=', $user->id)
                            ->count(),
                    ]);
                })->values();
        }

        return response()->json($conversations);
    }

    /**
     * Get or create a conversation.
     */
    public function findOrCreate(Request $request)
    {
        $request->validate([
            'shop_id'    => 'required|exists:shops,id',
        ]);

        $buyer = Auth::user();

        // Try to find existing conversation for this context
        $conversation = Conversation::where('buyer_id', $buyer->id)
            ->where('shop_id', $request->shop_id)
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'buyer_id'   => $buyer->id,
                'shop_id'    => $request->shop_id,
            ]);
        }

        $conversation->load(['buyer', 'shop']);

        return response()->json($conversation);
    }

    /**
     * Get all messages in a conversation.
     */
    public function messages(Request $request, $conversationId)
    {
        $user = Auth::user();
        $conversation = $this->getAuthorizedConversation($user, $conversationId);

        // Mark messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Update conversation read timestamp
        if ($user->shop && $user->shop->id === $conversation->shop_id) {
            $conversation->update(['seller_read_at' => now()]);
        } else {
            $conversation->update(['buyer_read_at' => now()]);
        }

        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar', 'order:id,total_amount', 'product:id,name'])
            ->get()
            ->map(function ($msg) {
                $msg->attachment_url = $msg->attachment_url;
                return $msg;
            });

        return response()->json([
            'conversation' => $conversation->load(['buyer', 'shop']),
            'messages'     => $messages,
        ]);
    }

    /**
     * Send a message in a conversation.
     */
    public function send(Request $request, $conversationId)
    {
        $request->validate([
            'body'       => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt',
            'order_id'   => 'nullable|exists:orders,id',
            'product_id' => 'nullable|exists:products,id',
        ]);

        if (!$request->body && !$request->hasFile('attachment')) {
            return response()->json(['message' => 'Un message ou une pièce jointe est requis.'], 422);
        }

        $user = Auth::user();
        $conversation = $this->getAuthorizedConversation($user, $conversationId);

        // Handle file upload
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('messages/attachments', 'public');
            $attachmentName = $file->getClientOriginalName();
            $attachmentType = str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'file';
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'body'            => $request->body,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'order_id'        => $request->order_id,
            'product_id'      => $request->product_id,
        ]);

        // Update conversation last_message_at
        $conversation->update(['last_message_at' => now(), 'status' => 'open']);

        $message->load('sender:id,name,avatar');
        $message->attachment_url = $message->attachment_url;

        // Notify the other party
        $isSeller = $user->shop && $user->shop->id === $conversation->shop_id;

        if ($isSeller) {
            // Seller sent message → notify buyer
            NotificationService::send(
                $conversation->buyer,
                'Nouveau message de ' . $user->shop->name,
                $request->body ? substr($request->body, 0, 100) : 'Pièce jointe reçue',
                'new_message',
                ['conversation_id' => $conversation->id]
            );
        } else {
            // Buyer sent message → notify seller
            $shop = Shop::with('user')->find($conversation->shop_id);
            if ($shop && $shop->user) {
                NotificationService::send(
                    $shop->user,
                    'Nouveau message de ' . $user->name,
                    $request->body ? substr($request->body, 0, 100) : 'Pièce jointe reçue',
                    'new_message',
                    ['conversation_id' => $conversation->id]
                );
            }
        }

        return response()->json($message, 201);
    }

    /**
     * Get total unread messages count for the authenticated user.
     */
    public function unreadCount()
    {
        $user = Auth::user();

        if ($user->shop) {
            // Seller
            $count = Message::whereHas('conversation', fn($q) => $q->where('shop_id', $user->shop->id))
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();
        } else {
            // Buyer
            $count = Message::whereHas('conversation', fn($q) => $q->where('buyer_id', $user->id))
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();
        }

        return response()->json(['unread' => $count]);
    }

    /**
     * Ensure the authenticated user has access to this conversation.
     */
    private function getAuthorizedConversation($user, $conversationId): Conversation
    {
        $conversation = Conversation::findOrFail($conversationId);

        $isBuyer  = $conversation->buyer_id === $user->id;
        $isSeller = $user->shop && $user->shop->id === $conversation->shop_id;

        if (!$isBuyer && !$isSeller) {
            abort(403, 'Accès non autorisé à cette conversation.');
        }

        return $conversation;
    }
}
