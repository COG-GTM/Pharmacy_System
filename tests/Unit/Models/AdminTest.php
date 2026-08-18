<?php

namespace Tests\Unit\Models;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The admins table has no migration, so the model is exercised against
        // a table created for the duration of the test.
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });
    }

    public function test_find_by_email_returns_the_matching_admin()
    {
        $admin = Admin::create(['email' => 'admin@example.com', 'password' => 'secret']);
        Admin::create(['email' => 'other@example.com', 'password' => 'secret']);

        $this->assertTrue(Admin::findByEmail('admin@example.com')->is($admin));
    }

    public function test_find_by_email_returns_null_for_an_unknown_email()
    {
        $this->assertNull(Admin::findByEmail('nobody@example.com'));
    }
}
