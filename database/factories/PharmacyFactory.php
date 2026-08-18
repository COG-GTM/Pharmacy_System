<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pharmacy>
 */
class PharmacyFactory extends Factory
{
    protected $model = Pharmacy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 999999),
            'user_id' => User::factory(),
            'pharmacy_name' => fake()->company().' Pharmacy',
            'avatar_image' => 'default-avatar.jpg',
            'area_id' => Area::factory(),
            'priority' => fake()->numberBetween(1, 10),
        ];
    }
}
