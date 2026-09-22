<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->post('api/v1/_probe', fn () => ['ok' => true]);

    $this->user = User::factory()->create();
    $this->hit = function (string $token, string $uri = 'api/v1/_probe') {
        // The sanctum guard caches its user per application; drop it so each call re-reads the bearer token.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson($uri);
    };
});

test('a token gets 60 requests a minute, then a rate_limited DomainError', function () {
    $token = $this->user->createToken('laptop')->plainTextToken;

    foreach (range(1, 60) as $attempt) {
        ($this->hit)($token)->assertOk();
    }

    ($this->hit)($token)
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limited')
        ->assertJsonStructure(['message', 'error' => ['code']]);
});

test('each token of one user has its own budget', function () {
    $laptop = $this->user->createToken('laptop')->plainTextToken;
    $ci = $this->user->createToken('ci')->plainTextToken;

    foreach (range(1, 60) as $attempt) {
        ($this->hit)($laptop)->assertOk();
    }

    ($this->hit)($laptop)->assertStatus(429);
    ($this->hit)($ci)->assertOk();
});

// routes/api.php repeats auth:sanctum and throttle:getid on the MCP route; a double count would halve the limit.
test('a route repeating the group middleware is counted once', function () {
    Route::middleware(['api', 'auth:sanctum', 'throttle:getid'])->post('mcp', fn () => ['ok' => true]);
    $token = $this->user->createToken('laptop')->plainTextToken;

    foreach (range(1, 60) as $attempt) {
        ($this->hit)($token, 'mcp')->assertOk();
    }

    ($this->hit)($token, 'mcp')->assertStatus(429);
});

test('REST and MCP draw from one budget per token', function () {
    Route::middleware('api')->post('mcp', fn () => ['ok' => true]);
    $token = $this->user->createToken('laptop')->plainTextToken;

    foreach (range(1, 30) as $attempt) {
        ($this->hit)($token)->assertOk();
        ($this->hit)($token, 'mcp')->assertOk();
    }

    ($this->hit)($token, 'mcp')->assertStatus(429);
});
