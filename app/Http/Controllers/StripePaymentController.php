<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePaymentController extends Controller
{
    /**
     * Show the payment page of an order owned by the authenticated user.
     *
     * @return \Illuminate\Http\Response
     */
    public function stripe($order_id)
    {
        $order = $this->findOwnedOrder($order_id);

        return view('stripe', ['order' => $order]);
    }

    /**
     * Charge the order and confirm it only once the charge succeeded.
     *
     * @return \Illuminate\Http\Response
     */
    public function stripePost(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'stripeToken' => ['required', 'string'],
        ]);

        $order = $this->findOwnedOrder($validated['order_id']);

        if ($order->status == 'WaitingForUserConfirmation') {
            try {
                $this->charge($order, $validated['stripeToken']);
            } catch (ApiErrorException | RuntimeException $exception) {
                Log::error('Stripe payment failed for order ' . $order->id . ': ' . $exception->getMessage());

                return back()->with('error', 'Payment failed, the order was not confirmed!')->with('timeout', 5000);
            }

            $order->update([
                'status' => 'Confirmed',
            ]);

            return view('actions.confirm', ['order' => $order, 'state' => 'Confirmednow']);
        } elseif ($order->status == 'Canceled') {
            return view('actions.confirm', ['order' => $order, 'state' => 'Canceled']);
        } elseif ($order->status == 'Confirmed') {
            return view('actions.confirm', ['order' => $order, 'state' => 'Confirmed']);
        } elseif ($order->status == 'Delivered') {
            return view('actions.confirm', ['order' => $order, 'state' => 'Delivered']);
        }
    }

    /**
     * Resolve an order that belongs to the authenticated user, or abort with a 404.
     */
    private function findOwnedOrder($orderId): Order
    {
        return Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    /**
     * Collect the order price through Stripe. Throws unless the charge succeeded.
     *
     * The idempotency key covers one order and one card token, so replaying the same
     * submission cannot charge twice while a retry with new card details still goes through.
     */
    private function charge(Order $order, string $stripeToken): void
    {
        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            throw new RuntimeException('Stripe secret key is not configured');
        }

        $amount = (int) round(((float) $order->price) * 100);
        if ($amount <= 0) {
            throw new RuntimeException('Order price is not payable');
        }

        $charge = app(StripeClient::class)->charges->create([
            'amount' => $amount,
            'currency' => 'usd',
            'source' => $stripeToken,
            'description' => "Pharmacy System order #{$order->id}",
        ], [
            'idempotency_key' => 'order-' . $order->id . '-' . hash('sha256', $stripeToken),
        ]);

        if ($charge->status !== 'succeeded' || $charge->amount !== $amount || $charge->currency !== 'usd') {
            throw new RuntimeException('Stripe charge was not completed');
        }
    }
}
