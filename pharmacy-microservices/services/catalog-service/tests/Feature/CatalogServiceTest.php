<?php

namespace Tests\Feature;

use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_medicine()
    {
        $medicine = Medicine::factory()->create();

        $response = $this->getJson('/api/medicines');
        $response->assertStatus(200);

        $response = $this->getJson("/api/medicines/{$medicine->id}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $medicine->name]);

        $response = $this->putJson("/api/medicines/{$medicine->id}", [
            'name' => 'Updated Medicine',
            'type' => 'tablet',
            'quantity' => 100,
            'price' => 1500,
        ]);
        $response->assertStatus(200);

        $response = $this->deleteJson("/api/medicines/{$medicine->id}");
        $response->assertStatus(200);
    }

    public function test_calculate_price_endpoint()
    {
        $med1 = Medicine::factory()->create(['price' => 1000]);
        $med2 = Medicine::factory()->create(['price' => 2500]);

        $response = $this->postJson('/api/medicines/calculate-price', [
            'medicine_ids' => [$med1->id, $med2->id],
            'quantities' => [3, 2],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total_price' => 80.0]);
    }
}
