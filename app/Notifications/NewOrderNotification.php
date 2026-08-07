<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification
{
    use Queueable;

    protected $order;
    protected $shop;

    public function __construct($order, $shop)
    {
        $this->order = $order;
        $this->shop = $shop;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_order',
            'title' => 'Nouvelle commande reçue !',
            'message' => 'Félicitations, vous avez reçu une nouvelle commande (#' . $this->order->id . ') sur votre boutique ' . $this->shop->name . '.',
            'order_id' => $this->order->id,
            'shop_id' => $this->shop->id,
        ];
    }
}
