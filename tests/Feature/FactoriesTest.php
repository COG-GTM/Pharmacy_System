<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<\Illuminate\Database\Eloquent\Model>}>
     */
    public function modelProvider()
    {
        return [
            'address' => [Address::class],
            'area' => [Area::class],
            'client' => [Client::class],
            'doctor' => [Doctor::class],
            'medicine' => [Medicine::class],
            'order' => [Order::class],
            'order medicine' => [OrderMedicine::class],
            'payment' => [Payment::class],
            'pharmacy' => [Pharmacy::class],
            'prescription' => [Prescription::class],
            'user' => [User::class],
        ];
    }

    /**
     * @dataProvider modelProvider
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public function test_factory_persists_the_model($model)
    {
        $instance = $model::factory()->create();

        $this->assertTrue($instance->exists);
        $this->assertDatabaseCount($instance->getTable(), 1);
    }
}
