<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicineFactory extends Factory
{
    protected $model = Medicine::class;

    public function definition()
    {
        return [
            'name' => fake()->word() . ' ' . fake()->randomElement(['Tablet', 'Capsule', 'Syrup', 'Cream']),
            'type' => fake()->randomElement(['Tablet', 'Capsule', 'Syrup', 'Cream', 'Injection']),
            'quantity' => fake()->numberBetween(10, 500),
            'price' => fake()->numberBetween(100, 10000),
        ];
    }
}
