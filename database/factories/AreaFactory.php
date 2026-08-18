<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition()
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 1000000),
            'name' => fake()->city(),
            'address' => fake()->streetAddress(),
            'country_id' => 1,
        ];
    }
}
