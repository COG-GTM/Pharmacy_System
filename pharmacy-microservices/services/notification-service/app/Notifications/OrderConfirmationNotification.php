<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification
{
    use Queueable;

    protected $orderData;

    public function __construct(array $orderData)
    {
        $this->orderData = $orderData;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->greeting('Order Confirmation')
            ->line("Your order #{$this->orderData['order_id']} has been confirmed.")
            ->line("Total: \${$this->orderData['price']}")
            ->action('View Order', url("/orders/{$this->orderData['order_id']}"));
    }
}
