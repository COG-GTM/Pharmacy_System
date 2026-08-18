<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_against_an_isolated_in_memory_database()
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_the_schema_is_migrated_and_starts_empty()
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('orders'));
        $this->assertSame(0, Order::count());
    }
}
