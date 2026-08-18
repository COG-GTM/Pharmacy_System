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

    public function definition()
    {
        return [
            'id' => fake()->unique()->numerify('29############'),
            'user_id' => User::factory(),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'date_of_birth' => fake()->date(),
            'avatar_image' => 'avatars/'.fake()->uuid().'.png',
            'phone' => fake()->numerify('01#########'),
            'email_verified_at' => now(),
        ];
    }

    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
