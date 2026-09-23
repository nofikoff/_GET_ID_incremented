<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Every REST route as [method, uri], read from the router so a route added later is covered without editing this file.
    $this->restRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (RouteDefinition $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => [$method, preg_replace('/\{[^}]+\}/', '1', $route->uri())]))
        ->values();

    $this->refusesEveryRoute = function (?string $token): void {
        expect($this->restRoutes->map(fn (array $route) => $route[1])->all())
            ->toContain('api/v1/sequence/next', 'api/v1/sequence/list');

        foreach ($this->restRoutes as [$method, $uri]) {
            // The sanctum guard caches its user per application; drop it so each call re-reads the header.
            $this->app['auth']->forgetGuards();
            $request = $token === null ? $this : $this->withToken($token);

            $request->json($method, $uri)
                ->assertStatus(401)
                ->assertJsonPath('error.code', 'unauthenticated');
        }
    };
});

test('every REST route refuses a request without a token', function () {
    ($this->refusesEveryRoute)(null);
});

test('every REST route refuses a revoked token', function () {
    $token = User::factory()->create()->createToken('laptop');
    $token->accessToken->delete();

    ($this->refusesEveryRoute)($token->plainTextToken);
});

test('every REST route refuses a token that was never issued', function () {
    ($this->refusesEveryRoute)('1|'.str_repeat('x', 40));
});

// FR-011: neither a refused nor an authenticated request about an unknown project registers it.
test('asking about an unknown project does not register it', function () {
    $query = ['project_key' => 'gitlab.cas.ai/team/sandbox', 'type' => 'ADR', 'name' => 'add-oauth-auth'];

    $this->postJson('api/v1/sequence/next', $query)->assertStatus(401);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('api/v1/sequence/next', $query)->assertStatus(422);
    $this->getJson('api/v1/sequence/list?'.http_build_query($query))->assertStatus(422);

    expect(Project::query()->count())->toBe(0);
});
