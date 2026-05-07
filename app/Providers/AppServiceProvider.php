<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\Website;
use App\Observers\WebsiteObserver;
use App\Http\Responses\LoginResponse as PlyrCardLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, PlyrCardLoginResponse::class);
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('Superadmin') ? true : null;
        });

        User::observe(UserObserver::class);

        Website::observe(WebsiteObserver::class);
    }
}