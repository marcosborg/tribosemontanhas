<?php

namespace App\Providers;

use App\Services\AdminDriverImpersonationService;
use Illuminate\Pagination\Paginator;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AdminDriverImpersonationService::class, function ($app) {
            return new AdminDriverImpersonationService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        View::composer(['partials.menu', 'layouts.admin'], function ($view) {
            $impersonationService = app(AdminDriverImpersonationService::class);

            $view->with('impersonationState', $impersonationService->viewState(auth()->user()));
        });
    }
}
