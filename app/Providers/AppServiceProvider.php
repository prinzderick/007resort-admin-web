<?php

namespace App\Providers;

use App\Auth\StaffSession;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireStaff;
use App\Services\R007Api\R007ApiClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(R007ApiClient::class, fn (Application $app) => new R007ApiClient(
            config: (array) $app['config']->get('r007.api', []) + ['mock' => (bool) $app['config']->get('r007.mock', false)],
            session: $app->bound('session.store') ? $app['session.store'] : null,
        ));

        $this->app->scoped(StaffSession::class, fn (Application $app) => new StaffSession($app['session.store']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire update requests must pass the same gates as the page that rendered them.
        Livewire::addPersistentMiddleware([RequireStaff::class, RequirePermission::class]);
    }
}
