<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $login = Str::lower((string) $request->input('login'));

            return [
                Limit::perMinute(10)->by(
                    'login-ip:' . $request->ip()
                ),

                Limit::perMinute(5)->by(
                    'login-identifier-ip:' . $login . '|' . $request->ip()
                ),
            ];
        });
    }
}