<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripePaymentController extends Controller
{
    /**
     * Show the payment page of an order owned by the authenticated user.
     *
     * @return \Illuminate\Http\Response
     */
    public function stripe(Request $request, $order_id)
    {
        $order = $this->findOwnedOrder($request, $order_id);

        return view('stripe', ['order' => $order]);
    }

    /**
     * Charge the authenticated user for their own order and confirm it.
     *
     * @return \Illuminate\Http\Response
     */
    public function stripePost(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'stripeToken' => ['required', 'string'],
        ]);

        $order = $this->findOwnedOrder($request, $validated['order_id']);

        if ($order->status == 'WaitingForUserConfirmation') {
            $this->charge($order, $validated['stripeToken']);
            $order->update([
                'status' => 'Confirmed'
            ]);

            return view('actions.confirm', ['order' => $order, 'state' => 'Confirmednow']);
        } elseif ($order->status == 'Canceled') {
            return view('actions.confirm', ['order' => $order, 'state' => 'Canceled']);
        } elseif ($order->status == 'Confirmed') {
            return view('actions.confirm', ['order' => $order, 'state' => 'Confirmed']);
        } elseif ($order->status == 'Delivered') {
            return view('actions.confirm', ['order' => $order, 'state' => 'Delivered']);
        }

        abort(404);
    }

    /**
     * Resolve an order that belongs to the authenticated user, 404 otherwise.
     *
     * @return \App\Models\Order
     */
    private function findOwnedOrder(Request $request, $order_id)
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        return $user->orders()->findOrFail($order_id);
    }

    /**
     * Collect the order payment through Stripe before it can be confirmed.
     *
     * @return void
     */
    private function charge(Order $order, string $stripeToken)
    {
        $secret = config('services.stripe.secret');

        if (empty($secret)) {
            Log::error('Stripe is not configured, refusing to confirm an unpaid order.', [
                'order_id' => $order->id,
            ]);
            abort(503, 'Payment processing is currently unavailable.');
        }

        Stripe::setApiKey($secret);

        try {
            $charge = Charge::create([
                'amount' => (int) round($order->price * 100),
                'currency' => 'usd',
                'source' => $stripeToken,
                'description' => "Pharmacy order #{$order->id}",
            ]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe charge failed.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);
            abort(402, 'Payment failed.');
        }

        if ($charge->status !== 'succeeded') {
            Log::error('Stripe charge was not successful.', [
                'order_id' => $order->id,
                'status' => $charge->status,
            ]);
            abort(402, 'Payment failed.');
        }
    }
}
