<?php

namespace Tests\Unit\Models;

use App\Models\Area;
use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_a_user_and_an_area()
    {
        $user = User::factory()->create();
        $area = Area::factory()->create();

        $pharmacy = Pharmacy::factory()->create(['user_id' => $user->id, 'area_id' => $area->id]);

        $this->assertTrue($pharmacy->user->is($user));
        $this->assertTrue($pharmacy->area->is($area));
    }

    public function test_it_has_many_doctors_and_orders()
    {
        $pharmacy = Pharmacy::factory()->create();
        $doctor = Doctor::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $order = Order::factory()->create(['pharmacy_id' => $pharmacy->id]);

        $this->assertEquals([$doctor->id], $pharmacy->doctors->pluck('id')->all());
        $this->assertEquals([$order->id], $pharmacy->orders->pluck('id')->all());
    }

    public function test_it_is_soft_deleted()
    {
        $pharmacy = Pharmacy::factory()->create();

        $pharmacy->delete();

        $this->assertSoftDeleted('pharmacies', ['id' => $pharmacy->id]);
        $this->assertNull(Pharmacy::find($pharmacy->id));
        $this->assertNotNull(Pharmacy::withTrashed()->find($pharmacy->id));
    }
}
