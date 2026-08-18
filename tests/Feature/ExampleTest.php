<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_the_login_page_is_reachable()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_the_dashboard_is_reachable_by_a_staff_user()
    {
        $this->actingAsRole('admin');

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
