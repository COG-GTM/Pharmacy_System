<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class StripePaymentController extends Controller
{
    protected string $orderServiceUrl;

    public function __construct()
    {
        $this->orderServiceUrl = env('ORDER_SERVICE_URL', 'http://order-service');
    }

    public function stripe($id)
    {
        $orderResponse = Http::get("{$this->orderServiceUrl}/api/orders/{$id}");

        if (!$orderResponse->successful()) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json([
            'order' => $orderResponse->json('order'),
            'stripe_key' => env('STRIPE_KEY'),
        ]);
    }

    public function stripePost(Request $request)
    {
        $orderId = $request->input('order_id');

        $orderResponse = Http::get("{$this->orderServiceUrl}/api/orders/{$orderId}");
        if (!$orderResponse->successful()) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order = $orderResponse->json('order');

        if ($order['status'] === 'WaitingForUserConfirmation') {
            $payment = Payment::create([
                'method' => 'stripe',
                'order_id' => $orderId,
            ]);

            $this->publishPaymentCompleted($payment->id, $orderId, $order['price'] ?? 0);

            return response()->json([
                'message' => 'Payment processed, order confirmed',
                'payment' => $payment,
            ]);
        }

        return response()->json([
            'message' => "Cannot process payment for order with status: {$order['status']}",
            'status' => $order['status'],
        ]);
    }

    private function publishPaymentCompleted(int $paymentId, int $orderId, float $amount)
    {
        try {
            $connection = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'rabbitmq'), 5672, 'guest', 'guest'
            );
            $channel = $connection->channel();
            $channel->exchange_declare('pharmacy_events', 'topic', false, true, false);

            $payload = json_encode([
                'event' => 'payment.completed',
                'data' => [
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                    'method' => 'stripe',
                    'amount' => $amount,
                    'completed_at' => now()->toIso8601String(),
                ],
            ]);

            $msg = new AMQPMessage($payload, ['delivery_mode' => 2]);
            $channel->basic_publish($msg, 'pharmacy_events', 'payment.completed');
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            \Log::error('Failed to publish payment.completed: ' . $e->getMessage());
        }
    }
}
