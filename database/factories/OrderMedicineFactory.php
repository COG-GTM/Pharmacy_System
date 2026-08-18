<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderMedicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderMedicine>
 */
class OrderMedicineFactory extends Factory
{
    protected $model = OrderMedicine::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'medicine_id' => Medicine::factory(),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
