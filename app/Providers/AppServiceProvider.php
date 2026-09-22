<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Keyed by token, not by user: a user holds one token per machine, and FR-020a budgets each token.
        RateLimiter::for('getid', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();

            return Limit::perMinute(60)->by(
                $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'ip:'.$request->ip(),
            );
        });
    }
}
