<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StateChangingRoutesTest extends TestCase
{
    /**
     * @dataProvider mutatingRoutes
     */
    public function test_state_changing_routes_are_not_reachable_over_get(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route [{$name}] is not registered.");
        $this->assertNotContains('GET', $route->methods(), "Route [{$name}] must not be reachable over GET.");
        $this->assertContains('POST', $route->methods(), "Route [{$name}] must be reachable over POST.");
    }

    public function mutatingRoutes(): array
    {
        return [
            'order cancellation' => ['orders.updatestatus'],
            'pharmacy restore' => ['pharmacies.restore'],
        ];
    }

    public function test_legacy_cancel_and_confirm_get_urls_no_longer_exist(): void
    {
        $this->get('/orders/stauts/1')->assertNotFound();
        $this->get('/orders/confirm/1')->assertNotFound();
        $this->get('/pharmacies/restore/1')->assertStatus(405);
    }

    public function test_cancellation_confirmation_page_is_a_get_route(): void
    {
        $route = Route::getRoutes()->getByName('orders.cancel');

        $this->assertNotNull($route, 'Route [orders.cancel] is not registered.');
        $this->assertContains('GET', $route->methods());
    }

    public function test_no_uri_is_excluded_from_csrf_verification(): void
    {
        $middleware = new \App\Http\Middleware\VerifyCsrfToken(app(), app('encrypter'));

        $except = (new \ReflectionProperty($middleware, 'except'))->getValue($middleware);

        $this->assertSame([], array_filter($except), 'No URI may opt out of CSRF verification.');
    }
}
