<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition()
    {
        return [
            'image' => 'prescription_' . fake()->uuid() . '.jpg',
            'order_id' => Order::factory(),
        ];
    }
}
