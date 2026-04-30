<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'avatar_image' => 'default-avatar.jpg',
            'pharmacy_id' => Pharmacy::factory(),
            'is_banned' => 0,
        ];
    }
}
