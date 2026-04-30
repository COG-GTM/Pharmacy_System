<?php

namespace Tests\Feature;

use App\Models\OrderStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_aggregation()
    {
        OrderStats::create(['order_id' => 1, 'pharmacy_id' => 1, 'status' => 'Delivered', 'price' => 50.00]);
        OrderStats::create(['order_id' => 2, 'pharmacy_id' => 1, 'status' => 'Delivered', 'price' => 30.00]);
        OrderStats::create(['order_id' => 3, 'pharmacy_id' => 2, 'status' => 'Delivered', 'price' => 100.00]);

        $response = $this->getJson('/api/revenue');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(2, $data);
    }

    public function test_chart_data()
    {
        OrderStats::create(['order_id' => 1, 'pharmacy_id' => 1, 'status' => 'New', 'price' => 10]);
        OrderStats::create(['order_id' => 2, 'pharmacy_id' => 1, 'status' => 'Delivered', 'price' => 20]);
        OrderStats::create(['order_id' => 3, 'pharmacy_id' => 1, 'status' => 'Delivered', 'price' => 30]);

        $response = $this->getJson('/api/charts/status');
        $response->assertStatus(200);
        $response->assertJsonStructure(['labels', 'data']);
    }

    public function test_consumes_order_delivered_event()
    {
        $command = new \App\Console\ConsumeOrderEventsCommand();

        OrderStats::updateOrCreate(
            ['order_id' => 99],
            [
                'pharmacy_id' => 5,
                'status' => 'Delivered',
                'price' => 75.50,
                'delivered_at' => now(),
            ]
        );

        $this->assertDatabaseHas('order_stats', [
            'order_id' => 99,
            'pharmacy_id' => 5,
            'status' => 'Delivered',
        ]);
    }
}
