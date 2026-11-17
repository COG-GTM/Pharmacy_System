<?php

namespace Tests\Unit\Models;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Medicine;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->integer('quantity');
            $table->double('price');
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('delivering_address_id')->nullable();
            $table->string('status');
            $table->boolean('is_insured');
            $table->string('creator_type');
            $table->double('price');
            $table->timestamps();
        });
        Schema::create('orders_medicines', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
            $table->primary(['order_id', 'medicine_id']);
            $table->timestamps();
        });
    }

    private function validateOrder(array $medicineIds, array $quantities): void
    {
        $request = StoreOrderRequest::create('/orders', 'POST', [
            'status' => 'Processing',
            'creator_type' => 'pharmacy',
            'medicine_id' => $medicineIds,
            'quantity' => $quantities,
        ]);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();
    }

    private function waitingOrder(array $medicines): Order
    {
        $order = Order::create([
            'user_id' => 1,
            'pharmacy_id' => 1,
            'status' => 'WaitingForUserConfirmation',
            'is_insured' => 0,
            'creator_type' => 'pharmacy',
            'price' => 0,
        ]);
        foreach ($medicines as $id => $quantity) {
            $order->medicines()->attach($id, ['quantity' => $quantity]);
        }

        return $order;
    }

    public function test_order_over_stock_is_rejected(): void
    {
        $insulin = Medicine::create(['name' => 'Insulin Pen', 'type' => 'Injection', 'quantity' => 10, 'price' => 2500]);

        try {
            $this->validateOrder([$insulin->id], [50]);
            $this->fail('An order for 50 with 10 in stock passed validation.');
        } catch (ValidationException $e) {
            $this->assertSame(['Only 10 Insulin Pen in stock, 50 requested.'], $e->errors()['quantity']);
        }
    }

    public function test_order_within_stock_passes(): void
    {
        $insulin = Medicine::create(['name' => 'Insulin Pen', 'type' => 'Injection', 'quantity' => 10, 'price' => 2500]);

        $this->validateOrder([$insulin->id], [10]);

        $this->assertTrue(true);
    }

    public function test_confirming_order_decrements_stock(): void
    {
        $insulin = Medicine::create(['name' => 'Insulin Pen', 'type' => 'Injection', 'quantity' => 10, 'price' => 2500]);
        $paracetamol = Medicine::create(['name' => 'Paracetamol', 'type' => 'Tablet', 'quantity' => 100, 'price' => 250]);
        $order = $this->waitingOrder([$insulin->id => 4, $paracetamol->id => 2]);

        $this->assertTrue($order->confirm());

        $this->assertSame('Confirmed', $order->fresh()->status);
        $this->assertEquals(6, $insulin->fresh()->quantity);
        $this->assertEquals(98, $paracetamol->fresh()->quantity);
        $this->assertFalse($order->confirm());
        $this->assertEquals(6, $insulin->fresh()->quantity);
    }

    public function test_confirming_order_over_stock_leaves_order_and_stock_unchanged(): void
    {
        $insulin = Medicine::create(['name' => 'Insulin Pen', 'type' => 'Injection', 'quantity' => 10, 'price' => 2500]);
        $order = $this->waitingOrder([$insulin->id => 50]);

        $this->assertFalse($order->confirm());

        $this->assertSame('WaitingForUserConfirmation', $order->fresh()->status);
        $this->assertEquals(10, $insulin->fresh()->quantity);
    }
}
