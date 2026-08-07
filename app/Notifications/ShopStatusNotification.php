<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShopStatusNotification extends Notification
{
    use Queueable;

    protected $shop;
    protected $status; // 'approved' ou 'rejected'

    public function __construct($shop, $status)
    {
        $this->shop = $shop;
        $this->status = $status;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $title = $this->status === 'approved' ? 'Boutique validée !' : 'Demande de boutique refusée';
        $message = $this->status === 'approved' 
            ? 'Félicitations, votre boutique "' . $this->shop->name . '" a été approuvée par l\'administrateur. Vous pouvez maintenant ajouter des produits !' 
            : 'Malheureusement, votre demande pour la boutique "' . $this->shop->name . '" a été refusée.';

        return [
            'type' => 'shop_status',
            'title' => $title,
            'message' => $message,
            'shop_id' => $this->shop->id,
            'status' => $this->status
        ];
    }
}
