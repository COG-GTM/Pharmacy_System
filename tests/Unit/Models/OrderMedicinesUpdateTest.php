<?php

namespace Tests\Unit\Models;

use App\Models\Medicine;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderMedicinesUpdateTest extends TestCase
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

    private function orderWith(array $medicines): Order
    {
        $order = Order::create([
            'user_id' => 1,
            'pharmacy_id' => 1,
            'status' => 'Processing',
            'is_insured' => 0,
            'creator_type' => 'pharmacy',
            'price' => 0,
        ]);
        Order::createOrderMedicine($order, array_values($medicines), array_keys($medicines));
        $order->price = Order::orderTotalPrice($order);
        $order->save();

        return $order;
    }

    private function lineItems(Order $order): array
    {
        return $order->medicines()->get()->pluck('pivot.quantity', 'id')->sort()->all();
    }

    public function test_editing_order_medicines_replaces_line_items_and_total(): void
    {
        $paracetamol = Medicine::create(['name' => 'Paracetamol', 'type' => 'Tablet', 'quantity' => 100, 'price' => 250]);
        $amoxicillin = Medicine::create(['name' => 'Amoxicillin', 'type' => 'Capsule', 'quantity' => 50, 'price' => 1200]);
        $ibuprofen = Medicine::create(['name' => 'Ibuprofen', 'type' => 'Tablet', 'quantity' => 80, 'price' => 400]);

        $order = $this->orderWith([$paracetamol->id => 2, $amoxicillin->id => 1]);
        $this->assertEquals(17, $order->price);

        Order::updateOrderMedicine($order, [1], [$ibuprofen->id]);

        $this->assertSame([$ibuprofen->id => 1], $this->lineItems($order));
        $this->assertEquals(4, $order->price);
    }

    public function test_editing_order_can_change_quantity_of_a_medicine_already_on_it(): void
    {
        $paracetamol = Medicine::create(['name' => 'Paracetamol', 'type' => 'Tablet', 'quantity' => 100, 'price' => 250]);
        $order = $this->orderWith([$paracetamol->id => 2]);

        Order::updateOrderMedicine($order, [5], [$paracetamol->id]);

        $this->assertSame([$paracetamol->id => 5], $this->lineItems($order));
        $this->assertEquals(12.5, $order->price);
    }

    public function test_editing_order_without_medicines_keeps_line_items_and_total(): void
    {
        $paracetamol = Medicine::create(['name' => 'Paracetamol', 'type' => 'Tablet', 'quantity' => 100, 'price' => 250]);
        $order = $this->orderWith([$paracetamol->id => 2]);
        $order->price = 0;

        Order::updateOrderMedicine($order, 0, null);

        $this->assertSame([$paracetamol->id => 2], $this->lineItems($order));
        $this->assertEquals(5, $order->price);
    }
}
