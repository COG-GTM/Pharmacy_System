<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class RevenueControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/revenue')->assertRedirect('/login');
    }

    public function test_a_pharmacy_sees_the_totals_of_its_delivered_orders()
    {
        $user = $this->actingAsRole('pharmacy');
        $pharmacy = Pharmacy::factory()->create(['user_id' => $user->id]);
        Order::factory()->status('Delivered')->create(['pharmacy_id' => $pharmacy->id, 'price' => 100]);
        Order::factory()->status('Delivered')->create(['pharmacy_id' => $pharmacy->id, 'price' => 50]);
        Order::factory()->status('New')->create(['pharmacy_id' => $pharmacy->id, 'price' => 999]);

        $this->get('/revenue')
            ->assertOk()
            ->assertViewIs('revenue.index')
            ->assertViewHas('orders', 2)
            ->assertViewHas('revenues', 150);
    }

    public function test_a_doctor_is_not_allowed_to_see_the_revenue()
    {
        $this->actingAsRole('doctor');

        $this->get('/revenue')->assertForbidden();
    }
}
