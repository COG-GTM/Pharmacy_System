<?php

namespace App\Listeners;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentCompletedListener
{
    public function handle(array $data)
    {
        $orderId = $data['order_id'] ?? null;
        if (!$orderId) {
            Log::error('PaymentCompletedListener: missing order_id');
            return;
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::error("PaymentCompletedListener: order {$orderId} not found");
            return;
        }

        if ($order->status === 'WaitingForUserConfirmation') {
            $order->update(['status' => 'Confirmed']);
            Log::info("Order {$orderId} confirmed via payment.completed event");
        }
    }
}
