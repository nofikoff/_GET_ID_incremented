<?php

use App\Http\Middleware\LogApiRequest;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

test('api routes authenticate, then share the getid limiter, then reach the journal', function () {
    $route = Route::middleware('api')->post('api/v1/_probe', fn () => ['ok' => true]);

    $middleware = app('router')->gatherRouteMiddleware($route);
    $position = fn (string $name): int|false => array_search($name, $middleware, true);

    expect($position(Authenticate::class.':sanctum'))->toBeInt()
        ->and($position(ThrottleRequests::class.':getid'))->toBeGreaterThan($position(Authenticate::class.':sanctum'))
        ->and($position(LogApiRequest::class))->toBeGreaterThan($position(ThrottleRequests::class.':getid'));
});
