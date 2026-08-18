<?php

namespace Tests\Feature\Web;

use App\Jobs\OrderConfirmationJob;
use App\Models\Address;
use App\Models\Client;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_a_client_may_not_manage_orders()
    {
        $this->actingAsRole('client');

        $this->get('/orders')->assertForbidden();
    }

    public function test_an_admin_sees_the_orders_index()
    {
        $this->actingAsRole('admin');
        Order::factory()->create();

        $this->get('/orders')
            ->assertOk()
            ->assertViewIs('order.index');
    }

    public function test_show_returns_the_order_with_its_related_records()
    {
        $this->actingAsRole('admin');
        $order = Order::factory()->create();
        $prescription = Prescription::factory()->create(['order_id' => $order->id]);

        $this->get("/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('pharmacy.id', $order->pharmacy_id)
            ->assertJsonPath('prescriptions.0.id', $prescription->id);
    }

    public function test_store_creates_an_order_with_its_medicines_and_total_price()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create();
        $address = Address::factory()->create(['client_id' => $client->id]);
        $pharmacy = Pharmacy::factory()->create();
        $medicine = Medicine::factory()->create(['price' => 250]);

        $this->post('/orders', [
            'user_id' => $client->user_id,
            'pharmacy_id' => $pharmacy->id,
            'doctor_id' => null,
            'creator_type' => 'pharmacy',
            'status' => 'Processing',
            'delivering_address_id' => $address->id,
            'medicine_id' => [$medicine->id],
            'quantity' => ['2'],
        ])
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        $order = Order::firstOrFail();
        $this->assertSame($pharmacy->id, $order->pharmacy_id);
        $this->assertEqualsWithDelta(5.0, (float) $order->price, 0.001);
        $this->assertSame(2, (int) $order->medicines()->first()->pivot->quantity);
    }

    public function test_store_rejects_an_address_that_belongs_to_another_client()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create();
        $foreignAddress = Address::factory()->create();
        $pharmacy = Pharmacy::factory()->create();
        $medicine = Medicine::factory()->create();

        $this->post('/orders', [
            'user_id' => $client->user_id,
            'pharmacy_id' => $pharmacy->id,
            'creator_type' => 'pharmacy',
            'status' => 'Processing',
            'delivering_address_id' => $foreignAddress->id,
            'medicine_id' => [$medicine->id],
            'quantity' => ['1'],
        ])
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('error', 'Delivery address doesn\'t belong to this client!');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_store_rejects_a_doctor_from_another_pharmacy()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create();
        $address = Address::factory()->create(['client_id' => $client->id]);
        $pharmacy = Pharmacy::factory()->create();
        $foreignDoctor = Doctor::factory()->create();
        $medicine = Medicine::factory()->create();

        $this->post('/orders', [
            'user_id' => $client->user_id,
            'pharmacy_id' => $pharmacy->id,
            'doctor_id' => $foreignDoctor->id,
            'creator_type' => 'pharmacy',
            'status' => 'Processing',
            'delivering_address_id' => $address->id,
            'medicine_id' => [$medicine->id],
            'quantity' => ['1'],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_store_dispatches_the_confirmation_job_for_orders_awaiting_confirmation()
    {
        Bus::fake();
        $this->actingAsRole('admin');
        $client = Client::factory()->create();
        $address = Address::factory()->create(['client_id' => $client->id]);
        $pharmacy = Pharmacy::factory()->create();
        $medicine = Medicine::factory()->create();

        $this->post('/orders', [
            'user_id' => $client->user_id,
            'pharmacy_id' => $pharmacy->id,
            'creator_type' => 'pharmacy',
            'status' => 'WaitingForUserConfirmation',
            'delivering_address_id' => $address->id,
            'medicine_id' => [$medicine->id],
            'quantity' => ['1'],
        ])->assertRedirect(route('orders.index'));

        Bus::assertDispatched(OrderConfirmationJob::class);
    }

    public function test_update_moves_the_order_to_waiting_for_user_confirmation()
    {
        Bus::fake();
        $this->actingAsRole('admin');
        $order = Order::factory()->status('Processing')->create();
        $medicine = Medicine::factory()->create(['price' => 100]);

        $this->put("/orders/{$order->id}", [
            'user_id' => $order->user_id,
            'pharmacy_id' => $order->pharmacy_id,
            'creator_type' => 'pharmacy',
            'status' => 'Processing',
            'delivering_address_id' => $order->delivering_address_id,
            'medicine_id' => [$medicine->id],
            'quantity' => ['3'],
        ])
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('WaitingForUserConfirmation', $order->status);
        $this->assertEqualsWithDelta(3.0, (float) $order->price, 0.001);
        Bus::assertDispatched(OrderConfirmationJob::class);
    }

    /**
     * Documents a live bug: order.edit reads $clients, which the controller
     * never passes to the view, so the edit page cannot render.
     */
    public function test_edit_fails_because_the_view_expects_a_clients_variable()
    {
        $this->actingAsRole('admin');
        $order = Order::factory()->create();

        $this->get("/orders/{$order->id}/edit")->assertStatus(500);
    }

    public function test_updatestatus_cancels_an_order_awaiting_confirmation()
    {
        $this->actingAsRole('admin');
        $order = Order::factory()->status('WaitingForUserConfirmation')->create();

        $this->get(route('orders.updatestatus', $order->id))
            ->assertOk()
            ->assertViewIs('actions.cancel')
            ->assertViewHas('state', 'WaitingForUserConfirmation');

        $this->assertSame('Canceled', $order->fresh()->status);
    }

    public function test_updatestatus_leaves_a_confirmed_order_untouched()
    {
        $this->actingAsRole('admin');
        $order = Order::factory()->status('Confirmed')->create();

        $this->get(route('orders.updatestatus', $order->id))
            ->assertOk()
            ->assertViewHas('state', 'Confirmed');

        $this->assertSame('Confirmed', $order->fresh()->status);
    }

    public function test_destroy_removes_the_order_with_its_medicines_and_prescriptions()
    {
        $this->actingAsRole('admin');
        $order = Order::factory()->create();
        $medicine = Medicine::factory()->create();
        $order->medicines()->attach($medicine->id, ['quantity' => 1]);
        Prescription::factory()->create(['order_id' => $order->id]);

        $this->delete("/orders/{$order->id}")
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('orders_medicines', ['order_id' => $order->id]);
        $this->assertDatabaseMissing('prescriptions', ['order_id' => $order->id]);
    }
}
