<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_an_authenticated_staff_member_sees_the_dashboard()
    {
        $this->actingAsRole('admin');

        $this->get('/home')
            ->assertOk()
            ->assertViewIs('home');
    }
}
