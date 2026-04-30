<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition()
    {
        return [
            'name' => fake()->city(),
            'address' => fake()->address(),
            'country_id' => 1,
        ];
    }
}
