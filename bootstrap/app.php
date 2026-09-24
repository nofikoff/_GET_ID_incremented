<?php

use App\Exceptions\RenderApiErrors;
use App\Http\ApiSurface;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // No automatic prefix: routes/api.php holds REST under /api/v1 and the MCP endpoint at /mcp.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The scheme only: enough for https links behind Cloudflare, while forwarded host and client address stay untrusted.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);

        // REST and MCP share this group, and with it one per-token budget of the getid limiter (FR-020a).
        $middleware->api(append: ['auth:sanctum', 'throttle:getid', LogApiRequest::class]);

        // FR-017: the administrator check runs before route model binding looks the id up.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureAdministrator::class);

        // Token clients get a 401, never a redirect to the sign-in page, even without an Accept header.
        $middleware->redirectGuestsTo(fn (Request $request): ?string => ApiSurface::includes($request) ? null : route('login'));

        // Explicit: the framework default is route('home'), which is the guest-only root itself and would loop.
        $middleware->redirectUsersTo(fn (): string => route('tokens.index'));
    })
    ->withExceptions(new RenderApiErrors)
    ->create();
