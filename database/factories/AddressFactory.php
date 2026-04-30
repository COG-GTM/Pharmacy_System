<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition()
    {
        return [
            'client_id' => Client::factory(),
            'area_id' => Area::factory(),
            'street_name' => fake()->streetName(),
            'building_number' => fake()->buildingNumber(),
            'floor_number' => fake()->numberBetween(1, 10),
            'flat_number' => fake()->numberBetween(1, 20),
            'is_main' => 0,
        ];
    }
}
