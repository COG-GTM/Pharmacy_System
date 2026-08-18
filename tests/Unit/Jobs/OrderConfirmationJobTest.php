<?php

namespace Tests\Unit\Jobs;

use App\Jobs\OrderConfirmationJob;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderConfirmationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_the_client_about_the_order()
    {
        Notification::fake();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        (new OrderConfirmationJob($user, $order))->handle();

        Notification::assertSentTo(
            $user,
            OrderConfirmationNotification::class,
            fn ($notification) => $notification->toMail($user) !== null
        );
    }

    public function test_it_can_be_dispatched_onto_the_queue()
    {
        Bus::fake();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        OrderConfirmationJob::dispatch($user, $order);

        Bus::assertDispatched(OrderConfirmationJob::class);
    }
}
