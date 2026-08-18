<?php

namespace Tests\Unit\Models;

use App\Models\Address;
use App\Models\Area;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_the_fillable_attributes_and_casts_the_id()
    {
        $area = Area::factory()->create(['id' => 42, 'name' => 'Nasr City']);

        $this->assertSame(42, $area->fresh()->id);
        $this->assertDatabaseHas('areas', ['id' => 42, 'name' => 'Nasr City']);
    }

    public function test_it_has_many_pharmacies_and_addresses()
    {
        $area = Area::factory()->create();
        $pharmacy = Pharmacy::factory()->create(['area_id' => $area->id]);
        $address = Address::factory()->create(['area_id' => $area->id]);
        Pharmacy::factory()->create();

        $this->assertEquals([$pharmacy->id], $area->pharmacies->pluck('id')->all());
        // areas.address is a column, so the relation must be queried explicitly.
        $this->assertEquals([$address->id], $area->address()->pluck('id')->all());
    }
}
