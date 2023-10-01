<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        view()->composer('*', function ($view) {
            $view->with('activeMenu', request()->segment(1));
            $view->with('activeSubMenu', request()->segment(2));
            $view->with('activeSubSubMenu', request()->segment(3));
        });
    }
}
