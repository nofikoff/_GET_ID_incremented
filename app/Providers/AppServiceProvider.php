<?php

namespace App\Providers;

use App\Models\KeyType;
use App\Models\Project;
use App\Models\User;
use App\Policies\KeyTypePolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
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

        // Sessions and remember-me cookies resolve users through this provider, so a deactivated employee's open
        // session stops authenticating on its next request and cannot mint a token past deactivation (FR-021b).
        Auth::provider('active-users', fn (Application $app, array $config): EloquentUserProvider => (new EloquentUserProvider($app->make('hash'), $config['model']))
            ->withQuery(fn (Builder $users) => $users->whereNull('deactivated_at')));

        // The default pagination markup needs a Tailwind build the host does not have; this one is a plain list the layout styles.
        Paginator::useBootstrapThree();

        Gate::define('administer', fn (User $user): bool => $user->isAdmin());
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(KeyType::class, KeyTypePolicy::class);
    }
}
