<?php

namespace App\Providers;

use App\Models\Exchange;
use App\Policies\ExchangePolicy;
use App\Models\Shipment;
use App\Policies\ShipmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Exchange::class => ExchangePolicy::class,
        Shipment::class => ShipmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
