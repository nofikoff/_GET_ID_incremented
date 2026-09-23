<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The role check for the administrative surface as a whole. bootstrap/app.php ranks it ahead of route
 * model binding, so a member is refused before any lookup and cannot tell which ids exist (FR-017).
 */
class EnsureAdministrator
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Gate::authorize('administer');

        return $next($request);
    }
}
