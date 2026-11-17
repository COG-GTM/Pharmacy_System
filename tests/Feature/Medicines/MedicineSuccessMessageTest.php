<?php

namespace Tests\Feature\Medicines;

use App\Models\Medicine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicineSuccessMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->integer('quantity');
            $table->double('price');
            $table->timestamps();
        });

        $this->withoutMiddleware();
    }

    public function test_creating_a_medicine_flashes_medicine_added(): void
    {
        $this->post(route('medicines.store'), [
            'name' => 'Paracetamol 500mg',
            'type' => 'Tablet',
            'quantity' => 40,
            'price' => '3.50',
        ])
            ->assertRedirect(route('medicines.index'))
            ->assertSessionHas('success', 'Medicine added successfully!');

        $this->assertDatabaseHas('medicines', ['name' => 'Paracetamol 500mg']);
    }

    public function test_updating_a_medicine_flashes_medicine_updated(): void
    {
        $medicine = Medicine::create(['name' => 'Amoxicillin', 'type' => 'Capsule', 'quantity' => 20, 'price' => 8]);

        $this->put(route('medicines.update', $medicine->id), [
            'name' => 'Amoxicillin 250mg',
            'type' => 'Capsule',
            'quantity' => 25,
            'price' => '8.25',
        ])
            ->assertRedirect(route('medicines.index'))
            ->assertSessionHas('success', 'Medicine updated successfully!');

        $this->assertDatabaseHas('medicines', ['id' => $medicine->id, 'name' => 'Amoxicillin 250mg']);
    }
}
