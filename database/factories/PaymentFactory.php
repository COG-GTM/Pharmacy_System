<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition()
    {
        return [
            'method' => fake()->randomElement(['stripe', 'cash']),
            'order_id' => Order::factory(),
        ];
    }
}
