<?php

namespace Tests\Feature\Web;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ChartControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/status/statusbarchart')->assertRedirect('/login');
    }

    public function test_an_admin_sees_the_counts_of_every_order_status()
    {
        $this->actingAsRole('admin');
        Order::factory()->count(2)->status('New')->create();
        Order::factory()->status('Delivered')->create();

        $this->get('/status/statusbarchart')
            ->assertOk()
            ->assertJson([
                'labels' => ['New', 'Processing', 'WaitingForUserConfirmation', 'Canceled', 'Delivered'],
                'data' => [2, 0, 0, 0, 1],
            ]);
    }

    public function test_a_pharmacy_only_sees_the_counts_of_its_own_orders()
    {
        $user = $this->actingAsRole('pharmacy');
        $pharmacy = Pharmacy::factory()->create(['user_id' => $user->id]);
        Order::factory()->status('New')->create(['pharmacy_id' => $pharmacy->id]);
        Order::factory()->status('New')->create();

        $this->get('/status/statuspiechart')
            ->assertOk()
            ->assertJsonPath('data', [1, 0, 0, 0, 0]);
    }

    public function test_a_doctor_sees_the_counts_of_the_orders_of_their_pharmacy()
    {
        $user = $this->actingAsRole('doctor');
        $pharmacy = Pharmacy::factory()->create();
        Doctor::factory()->create(['user_id' => $user->id, 'pharmacy_id' => $pharmacy->id]);
        Order::factory()->status('Canceled')->create(['pharmacy_id' => $pharmacy->id]);

        $this->get('/status/statusbarchart')
            ->assertOk()
            ->assertJsonPath('data', [0, 0, 0, 1, 0]);
    }
}
