<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition()
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 1000000),
            'user_id' => User::factory(),
            'pharmacy_id' => Pharmacy::factory(),
            'avatar_image' => 'default.jpg',
            'is_banned' => false,
        ];
    }

    public function banned()
    {
        return $this->state(fn (array $attributes) => [
            'is_banned' => true,
        ]);
    }
}
