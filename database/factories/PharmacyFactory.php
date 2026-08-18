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

    public function definition()
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 1000000),
            'user_id' => User::factory(),
            'area_id' => Area::factory(),
            'pharmacy_name' => fake()->company().' Pharmacy',
            'avatar_image' => 'default.jpg',
            'priority' => fake()->numberBetween(1, 10),
        ];
    }
}
