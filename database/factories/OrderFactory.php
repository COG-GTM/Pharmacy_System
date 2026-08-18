<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'pharmacy_id' => Pharmacy::factory(),
            'delivering_address_id' => Address::factory(),
            'doctor_id' => null,
            'status' => 'New',
            'creator_type' => 'client',
            'is_insured' => false,
            'price' => fake()->numberBetween(1, 1000),
        ];
    }

    public function status(string $status)
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
