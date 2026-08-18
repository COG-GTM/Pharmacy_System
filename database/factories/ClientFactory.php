<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

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
            'gender' => fake()->randomElement(['Male', 'Female']),
            'date_of_birth' => fake()->date(),
            'avatar_image' => 'default-avatar.jpg',
            'phone' => fake()->numerify('01#########'),
            'email_verified_at' => now(),
        ];
    }

    /**
     * Indicate that the client's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
