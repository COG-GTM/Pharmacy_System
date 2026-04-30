<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PharmacyFactory extends Factory
{
    protected $model = Pharmacy::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'pharmacy_name' => fake()->company(),
            'avatar_image' => 'default-avatar.jpg',
            'area_id' => Area::factory(),
            'priority' => fake()->numberBetween(1, 10),
        ];
    }
}
