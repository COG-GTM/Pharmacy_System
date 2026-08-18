<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Medicine>
 */
class MedicineFactory extends Factory
{
    protected $model = Medicine::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(['pill', 'syrup', 'injection', 'cream']),
            'quantity' => fake()->numberBetween(1, 500),
            'price' => fake()->numberBetween(100, 10000), // stored in cents
        ];
    }
}
