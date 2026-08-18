<?php

namespace Database\Factories;

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

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'pharmacy_id' => Pharmacy::factory(),
            'status' => 'New',
            'is_insured' => false,
            'creator_type' => 'client',
            'price' => fake()->numberBetween(1, 500),
        ];
    }

    /**
     * Set the order status.
     *
     * @param  string  $status
     * @return static
     */
    public function status($status)
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
