<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition()
    {
        return [
            'image' => 'prescriptions/'.fake()->uuid().'.jpg',
            'order_id' => Order::factory(),
        ];
    }
}
