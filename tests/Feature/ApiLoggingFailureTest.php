<?php

use App\Models\ApiLog;
use App\Models\Identifier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

// FR-026: a journal that cannot be written costs an entry, never an issued number or the response carrying it.

beforeEach(function () {
    $this->travelTo('2026-09-20 14:30:00');
    enabledPair();
    $this->withToken(User::factory()->create()->createToken('laptop')->plainTextToken);

    ApiLog::creating(fn () => throw new RuntimeException('api_logs is unavailable'));
    Log::spy();

    $this->journalFailureWasLogged = fn () => Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context = []): bool => str_contains($message, 'journal')
            && ($context['exception'] ?? null) instanceof RuntimeException,
    );
});

test('a REST client gets its issued number, unchanged, when the entry cannot be written', function () {
    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'])
        ->assertOk()
        ->assertExactJson([
            'project_key' => 'gitlab.cas.ai/team/backend',
            'type' => 'ADR',
            'name' => 'add-oauth-auth',
            'sequence_number' => 1,
            'is_new' => true,
            'created_at' => '2026-09-20T14:30:00Z',
        ]);

    expect(Identifier::query()->count())->toBe(1);
    ($this->journalFailureWasLogged)();
});

test('an MCP client gets its issued number when the entry cannot be written', function () {
    $this->mcpTool('next_id', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.sequence_number', 1);

    expect(Identifier::query()->count())->toBe(1);
    ($this->journalFailureWasLogged)();
});
