<?php

namespace Tests\Feature;

use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class MedicineControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/medicines')->assertRedirect('/login');
    }

    public function test_show_returns_the_medicine_as_json()
    {
        $this->actingAsRole('admin');
        $medicine = Medicine::factory()->create(['name' => 'Panadol']);

        $this->get("/medicines/{$medicine->id}")
            ->assertOk()
            ->assertJsonPath('medicine.0.name', 'Panadol');
    }

    public function test_store_persists_a_medicine_and_redirects_to_the_index()
    {
        $this->actingAsRole('admin');

        $this->post('/medicines', [
            'name' => 'Aspirin',
            'type' => 'pill',
            'quantity' => 10,
            'price' => 500,
        ])->assertRedirect(route('medicines.index'));

        $this->assertDatabaseHas('medicines', ['name' => 'Aspirin', 'quantity' => 10]);
    }

    public function test_update_changes_the_medicine()
    {
        $this->actingAsRole('admin');
        $medicine = Medicine::factory()->create(['quantity' => 1]);

        $this->put("/medicines/{$medicine->id}", [
            'name' => $medicine->name,
            'type' => $medicine->type,
            'quantity' => 42,
            'price' => $medicine->price,
        ])->assertRedirect(route('medicines.index'));

        $this->assertDatabaseHas('medicines', ['id' => $medicine->id, 'quantity' => 42]);
    }

    public function test_destroy_removes_the_medicine()
    {
        $this->actingAsRole('admin');
        $medicine = Medicine::factory()->create();

        $this->delete("/medicines/{$medicine->id}")
            ->assertRedirect(route('medicines.index'));

        $this->assertDatabaseMissing('medicines', ['id' => $medicine->id]);
    }
}
