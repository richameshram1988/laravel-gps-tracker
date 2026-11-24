<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
//use Illuminate\Support\Facades\Gate;

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
        // Gate::define(
        //     'view-report',
        //     fn(User $auth, User $target) =>
        //         $auth->is_admin || $auth->id === $target->id
        // );

    }
}
