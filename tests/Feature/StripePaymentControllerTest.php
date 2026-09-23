<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StripePaymentControllerTest extends TestCase
{
    private User $owner;

    private User $attacker;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->createSchema();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('secret-owner'),
        ]);
        $this->attacker = User::create([
            'name' => 'Attacker',
            'email' => 'attacker@example.test',
            'password' => Hash::make('secret-attacker'),
        ]);
        $this->order = Order::create([
            'user_id' => $this->owner->id,
            'pharmacy_id' => 1,
            'status' => 'WaitingForUserConfirmation',
            'is_insured' => false,
            'creator_type' => 'client',
            'price' => 25.5,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_another_user_cannot_view_the_payment_page_of_an_order(): void
    {
        $response = $this->actingAs($this->attacker)->get('/stripe/' . $this->order->id);

        $response->assertNotFound();
    }

    public function test_another_user_cannot_confirm_an_order(): void
    {
        $response = $this->actingAs($this->attacker)->post('/stripe', [
            'order_id' => $this->order->id,
            'stripeToken' => 'tok_visa',
        ]);

        $response->assertNotFound();
        $this->assertSame('WaitingForUserConfirmation', $this->order->fresh()->status);
    }

    public function test_owner_cannot_confirm_an_order_without_a_collected_payment(): void
    {
        config(['services.stripe.secret' => null]);

        $response = $this->actingAs($this->owner)->post('/stripe', [
            'order_id' => $this->order->id,
            'stripeToken' => 'tok_visa',
        ]);

        $response->assertStatus(503);
        $this->assertSame('WaitingForUserConfirmation', $this->order->fresh()->status);
    }

    public function test_confirmation_requires_a_payment_token(): void
    {
        $response = $this->actingAs($this->owner)->post('/stripe', [
            'order_id' => $this->order->id,
        ]);

        $response->assertStatus(302);
        $this->assertSame('WaitingForUserConfirmation', $this->order->fresh()->status);
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->string('status');
            $table->boolean('is_insured');
            $table->string('creator_type');
            $table->double('price');
            $table->timestamps();
        });

        DB::table('users')->truncate();
        DB::table('orders')->truncate();
    }
}
