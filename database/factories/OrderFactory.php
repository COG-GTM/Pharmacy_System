<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'doctor_id' => null,
            'delivering_address_id' => Address::factory(),
            'pharmacy_id' => Pharmacy::factory(),
            'is_insured' => fake()->boolean(),
            'status' => fake()->randomElement(['New', 'Processing', 'WaitingForUserConfirmation', 'Confirmed', 'Delivered', 'Canceled']),
            'creator_type' => 'pharmacy',
            'price' => fake()->numberBetween(100, 10000) / 100,
        ];
    }
}
