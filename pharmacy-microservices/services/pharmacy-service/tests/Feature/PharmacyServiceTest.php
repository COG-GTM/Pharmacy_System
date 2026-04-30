<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PharmacyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_pharmacy()
    {
        $pharmacy = Pharmacy::factory()->create();

        $response = $this->getJson('/api/pharmacies');
        $response->assertStatus(200);

        $response = $this->getJson("/api/pharmacies/{$pharmacy->id}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['pharmacy_name' => $pharmacy->pharmacy_name]);

        $response = $this->putJson("/api/pharmacies/{$pharmacy->id}", [
            'pharmacy_name' => 'Updated Name',
        ]);
        $response->assertStatus(200);
    }

    public function test_crud_doctor()
    {
        $pharmacy = Pharmacy::factory()->create();
        $doctor = Doctor::factory()->create(['pharmacy_id' => $pharmacy->id]);

        $response = $this->getJson('/api/doctors');
        $response->assertStatus(200);

        $response = $this->getJson("/api/doctors/{$doctor->id}");
        $response->assertStatus(200);

        $response = $this->putJson("/api/doctors/{$doctor->id}", [
            'avatar_image' => 'new-avatar.jpg',
        ]);
        $response->assertStatus(200);
    }

    public function test_get_pharmacy_by_area()
    {
        $pharmacy1 = Pharmacy::factory()->create(['area_id' => 1, 'priority' => 10]);
        $pharmacy2 = Pharmacy::factory()->create(['area_id' => 1, 'priority' => 5]);
        $pharmacy3 = Pharmacy::factory()->create(['area_id' => 2, 'priority' => 8]);

        $response = $this->getJson('/api/pharmacies/by-area/1');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(2, $data);
        $this->assertEquals($pharmacy1->id, $data[0]['id']);
    }

    public function test_ban_doctor_calls_auth_service()
    {
        Http::fake([
            '*/api/users/*/ban' => Http::response(['message' => 'User banned'], 200),
        ]);

        $doctor = Doctor::factory()->create(['is_banned' => false]);

        $response = $this->postJson("/api/doctors/{$doctor->id}/ban");
        $response->assertStatus(200);
        $this->assertTrue((bool) $doctor->fresh()->is_banned);
    }

    public function test_destroy_pharmacy_checks_order_service()
    {
        Http::fake([
            '*/api/orders*' => Http::response(['data' => []], 200),
            '*/api/users/*' => Http::response(['message' => 'deleted'], 200),
        ]);

        $pharmacy = Pharmacy::factory()->create();

        $response = $this->deleteJson("/api/pharmacies/{$pharmacy->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('pharmacies', ['id' => $pharmacy->id]);
    }
}
