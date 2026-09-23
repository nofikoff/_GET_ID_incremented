<?php

use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// FR-025: who of the colleagues called, with which token, what, and what came back. FR-024a: MCP on a par with REST.
// Payloads compare with toEqual: MySQL's json column stores object keys in its own order.

beforeEach(function () {
    enabledPair();
    $this->ada = User::factory()->create();
    $this->withToken($this->ada->createToken('laptop')->plainTextToken);
});

test('an issuance over REST leaves an entry with user, token name, endpoint, parameters, status and duration', function () {
    $input = ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'];

    $this->postJson('api/v1/sequence/next', $input)->assertOk();

    expect(ApiLog::query()->sole())
        ->user_id->toBe($this->ada->id)
        ->token_name->toBe('laptop')
        ->method->toBe('POST')
        ->endpoint->toBe('/api/v1/sequence/next')
        ->payload->toEqual($input)
        ->status_code->toBe(200)
        ->duration_ms->toBeInt()->toBeGreaterThanOrEqual(0)
        ->created_at->not->toBeNull();
});

test('a read over REST records its query parameters', function () {
    $this->getJson('api/v1/sequence/list?project_key=gitlab.cas.ai/team/backend&type=ADR')->assertOk();

    expect(ApiLog::query()->sole())
        ->method->toBe('GET')
        ->endpoint->toBe('/api/v1/sequence/list')
        ->payload->toEqual(['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR']);
});

test('a refused request is recorded with its status', function () {
    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/sandbox', 'type' => 'ADR', 'name' => 'x'])->assertStatus(422);

    expect(ApiLog::query()->sole()->status_code)->toBe(422);
});

test('a call over MCP leaves an entry with the tool and its arguments', function () {
    $this->mcpTool('next_id', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'])->assertOk();

    $log = ApiLog::query()->sole();
    expect($log)
        ->user_id->toBe($this->ada->id)
        ->token_name->toBe('laptop')
        ->method->toBe('POST')
        ->endpoint->toBe('/mcp')
        ->status_code->toBe(200)
        ->and($log->payload['method'])->toBe('tools/call')
        ->and($log->payload['params'])->toEqual([
            'name' => 'next_id',
            'arguments' => ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'],
        ]);
});

test('the duration covers the handling of the request', function () {
    Route::middleware('api')->post('api/v1/_slow', function () {
        usleep(60_000);

        return ['ok' => true];
    });

    $this->postJson('api/v1/_slow')->assertOk();

    expect(ApiLog::query()->sole()->duration_ms)->toBeGreaterThanOrEqual(60);
});

// The journal answers "which colleague", which the account and token do; an address behind the CDN answers nothing.
test('no network address is recorded', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeader('X-Forwarded-For', '198.51.100.9')
        ->getJson('api/v1/sequence/list?project_key=gitlab.cas.ai/team/backend&type=ADR')
        ->assertOk();

    expect(Schema::getColumnListing('api_logs'))
        ->toEqualCanonicalizing(['id', 'user_id', 'token_name', 'method', 'endpoint', 'payload', 'status_code', 'duration_ms', 'created_at'])
        ->and(json_encode(ApiLog::query()->sole()->getAttributes()))
        ->not->toContain('203.0.113.7')
        ->not->toContain('198.51.100.9');
});

// data-model.md api_logs: a snapshot of the name, because deactivation deletes the tokens entries are read against.
test('an entry keeps the token name after the token is gone', function () {
    $this->getJson('api/v1/sequence/list?project_key=gitlab.cas.ai/team/backend&type=ADR')->assertOk();

    $this->ada->tokens()->delete();

    expect(ApiLog::query()->sole()->token_name)->toBe('laptop');
});

test('a request refused before authentication leaves no entry', function () {
    $this->withToken('1|'.str_repeat('x', 40))
        ->getJson('api/v1/sequence/list?project_key=gitlab.cas.ai/team/backend&type=ADR')
        ->assertUnauthorized();

    expect(ApiLog::query()->count())->toBe(0);
});

test('a large payload is cut to 4 KB and still stored as valid JSON', function () {
    $name = str_repeat('Длинная тема ', 1000);

    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => $name])->assertStatus(422);

    $payload = ApiLog::query()->sole()->payload;
    expect(strlen(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))->toBeLessThanOrEqual(4096)
        ->and($payload['truncated'])->toBeTrue()
        ->and($payload['bytes'])->toBeGreaterThan(4096)
        ->and($payload['head'])->toStartWith('{"project_key":"gitlab.cas.ai/team/backend","type":"ADR","name":"Длинная тема')
        ->and(mb_check_encoding($payload['head'], 'UTF-8'))->toBeTrue();
});
