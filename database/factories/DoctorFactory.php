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
            'pharmacy_id' => Pharmacy::factory(),
            'avatar_image' => 'default-avatar.jpg',
            'is_banned' => false,
        ];
    }
}
