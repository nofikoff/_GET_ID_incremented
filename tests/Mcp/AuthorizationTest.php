<?php

use App\Models\Identifier;
use App\Models\User;

// FR-022: the MCP route is authorized by the same Sanctum token as REST, at the route, before any tool runs.

beforeEach(function () {
    enabledPair();
    $this->issue = fn () => $this->mcpTool('next_id', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth']);
});

test('a call without an Authorization header is refused at the route and reaches no tool', function () {
    ($this->issue)()
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated')
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');

    expect(Identifier::query()->count())->toBe(0);
});

test('a revoked token is refused at the route', function () {
    $token = User::factory()->create()->createToken('laptop');
    $token->accessToken->delete();

    $this->withToken($token->plainTextToken);
    ($this->issue)()->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');

    expect(Identifier::query()->count())->toBe(0);
});

test('a valid token reaches the tool', function () {
    $this->withToken(User::factory()->create()->createToken('laptop')->plainTextToken);

    ($this->issue)()->assertOk()->assertJsonPath('result.structuredContent.sequence_number', 1);
});
