<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Policies\DoctorPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PharmacyPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Doctor::class => DoctorPolicy::class,
        Order::class => OrderPolicy::class,
        Pharmacy::class => PharmacyPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
