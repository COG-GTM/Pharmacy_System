<?php

namespace Tests\Unit\Models;

use App\Models\Doctor;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_a_user_and_a_pharmacy()
    {
        $user = User::factory()->create();
        $pharmacy = Pharmacy::factory()->create();

        $doctor = Doctor::factory()->create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacy->id,
        ]);

        $this->assertTrue($doctor->user->is($user));
        $this->assertTrue($doctor->pharmacy->is($pharmacy));
    }

    /**
     * Documents a live gap: the model is Bannable but the doctors table has no
     * banned_at column, so banning a doctor fails at the database level.
     */
    public function test_banning_a_doctor_fails_because_the_table_has_no_banned_at_column()
    {
        $doctor = Doctor::factory()->create();

        $this->assertFalse($doctor->isBanned());

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/no such column: banned_at/');

        $doctor->ban();
    }

    public function test_the_password_attribute_is_hidden_from_serialization()
    {
        $doctor = Doctor::factory()->create();

        $this->assertArrayNotHasKey('password', $doctor->toArray());
    }
}
