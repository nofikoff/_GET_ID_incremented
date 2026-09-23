<?php

use App\Models\User;

// The limiter itself is pinned on a probe route in tests/Feature/Http/RateLimiterTest.php; this runs it on real endpoints.

// FR-020a: a looping agent must be able to tell "slow down" from "not allowed".
test('an exhausted budget answers rate_limited, distinct from the forbidden of a refused operation', function () {
    $token = User::factory()->create()->createToken('laptop')->plainTextToken;
    $hit = function (string $uri) use ($token) {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson($uri);
    };

    // Refused operations spend the budget too: the limiter runs before the role check.
    foreach (range(1, 60) as $attempt) {
        $hit('api/v1/admin/projects')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
    }

    $hit('api/v1/admin/projects')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limited');
    $hit('api/v1/sequence/list?project_key=gitlab.cas.ai/team/backend&type=ADR')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
});
