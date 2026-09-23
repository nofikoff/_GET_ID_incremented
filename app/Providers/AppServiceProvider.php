<?php

namespace App\Providers;

use App\Models\KeyType;
use App\Models\Project;
use App\Models\User;
use App\Policies\KeyTypePolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        Gate::define('administer', fn (User $user): bool => $user->isAdmin());
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(KeyType::class, KeyTypePolicy::class);
    }
}
