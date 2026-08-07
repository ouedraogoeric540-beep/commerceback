<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OutOfStockNotification extends Notification
{
    use Queueable;

    protected $product;

    /**
     * Create a new notification instance.
     */
    public function __construct($product)
    {
        $this->product = $product;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'out_of_stock',
            'title' => 'Produit en rupture de stock',
            'message' => 'Votre produit "' . $this->product->title . '" est désormais en rupture de stock (0 exemplaire restant).',
            'product_id' => $this->product->id,
            'shop_id' => $this->product->shop_id,
        ];
    }
}
