<?php

use App\Models\Identifier;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// FR-024: each tool is compared with its endpoint on one registry, over HTTP with one token, as the two clients call them.

beforeEach(function () {
    $this->travelTo('2026-09-20 14:30:00');
    $this->withToken(User::factory()->create()->createToken('laptop')->plainTextToken);
    $this->pair = enabledPair(counter: ['seed_sequence' => 11]);

    // Runs a call and undoes what it wrote, so the other transport starts from the very same registry.
    $this->undone = function (Closure $call): mixed {
        DB::beginTransaction();

        try {
            return $call();
        } finally {
            DB::rollBack();
        }
    };

    $this->toolResult = function (string $tool, array $arguments): array {
        $result = $this->mcpTool($tool, $arguments)->assertOk()->json('result');
        expect($result['isError'])->toBeFalse()
            ->and(json_decode($result['content'][0]['text'], true))->toBe($result['structuredContent']);

        return $result['structuredContent'];
    };

    $this->toolRefusal = function (string $tool, array $arguments): string {
        $result = $this->mcpTool($tool, $arguments)->assertOk()->json('result');
        expect($result['isError'])->toBeTrue();

        return $result['content'][0]['text'];
    };
});

// The padding is deliberate: HTTP trims string input in the kernel, and a tool must answer as if it did too.
test('next_id issues what POST /sequence/next would have issued, first time and on repeat', function () {
    $input = ['project_key' => ' git@gitlab.cas.ai:team/backend.git', 'type' => ' ADR ', 'name' => "  Add OAuth Auth\t"];

    $rest = ($this->undone)(fn () => $this->postJson('api/v1/sequence/next', $input)->assertOk()->json());
    $mcp = ($this->toolResult)('next_id', $input);

    expect($mcp)->toBe($rest)->and($mcp['sequence_number'])->toBe(12)->and($mcp['is_new'])->toBeTrue();

    $repeat = ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'];
    expect(($this->toolResult)('next_id', $repeat))
        ->toBe($this->postJson('api/v1/sequence/next', $repeat)->assertOk()->json())
        ->toMatchArray(['sequence_number' => 12, 'name' => 'Add OAuth Auth', 'is_new' => false]);
});

test('list_identifiers returns what GET /sequence/list returns', function () {
    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'first'])->assertOk();
    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'second'])->assertOk();
    $input = ['project_key' => 'GitLab.cas.ai/team/backend', 'type' => 'ADR'];

    expect(($this->toolResult)('list_identifiers', $input))
        ->toBe($this->getJson('api/v1/sequence/list?'.http_build_query($input))->assertOk()->json())
        ->toHaveKey('items.1.formatted_id', 'ADR-0012');
});

test('resolve_project returns what GET /projects/resolve returns', function (string $origin) {
    expect(($this->toolResult)('resolve_project', ['origin' => $origin]))
        ->toBe($this->getJson('api/v1/projects/resolve?'.http_build_query(['origin' => $origin]))->assertOk()->json());
})->with([
    'registered' => 'git@gitlab.cas.ai:team/backend.git',
    'unregistered' => 'https://gitlab.cas.ai/team/sandbox.git',
]);

// The four reasons FR-015 tells apart, plus the two refusals issuance shares with them.
test('next_id refuses for the same reason, with the same code and text, as POST /sequence/next', function (Closure $arrange, array $input, string $code) {
    $arrange($this->pair);
    $input = ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth', ...$input];

    $rest = $this->postJson('api/v1/sequence/next', $input)->assertStatus(422)->assertJsonPath('error.code', $code)->json();

    expect(($this->toolRefusal)('next_id', $input))->toBe("{$code}: {$rest['message']}");
})->with([
    'project not registered' => [fn () => null, ['project_key' => 'gitlab.cas.ai/team/sandbox'], 'project_not_registered'],
    'project retired' => [fn (ProjectKeyType $pair) => $pair->project->update(['is_active' => false]), [], 'project_inactive'],
    'type not enabled' => [fn (ProjectKeyType $pair) => $pair->update(['is_enabled' => false]), [], 'type_not_enabled'],
    'type retired' => [fn (ProjectKeyType $pair) => $pair->keyType->update(['is_active' => false]), [], 'type_inactive'],
    'origin unparsable' => [fn () => null, ['project_key' => 'not a repository'], 'origin_unparsable'],
    'theme empty after normalization' => [fn () => null, ['name' => ' _.- '], 'name_empty_after_normalization'],
]);

// The tool validates through the endpoint's FormRequest, so the contract's closed body closes the arguments too.
test('next_id refuses an argument the contract does not declare, as POST /sequence/next refuses the field', function () {
    $input = ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth', 'sequence_number' => 7];

    $rest = $this->postJson('api/v1/sequence/next', $input)->assertStatus(422)->assertJsonValidationErrors(['sequence_number'])->json();

    expect(($this->toolRefusal)('next_id', $input))->toBe($rest['message'])
        ->and(Identifier::query()->count())->toBe(0);
});

test('list_identifiers and resolve_project refuse as their endpoints do', function (string $tool, array $input, string $uri, string $code) {
    $rest = $this->getJson($uri.'?'.http_build_query($input))->assertStatus(422)->assertJsonPath('error.code', $code)->json();

    expect(($this->toolRefusal)($tool, $input))->toBe("{$code}: {$rest['message']}");
})->with([
    'list, project not registered' => ['list_identifiers', ['project_key' => 'gitlab.cas.ai/team/sandbox', 'type' => 'ADR'], 'api/v1/sequence/list', 'project_not_registered'],
    'list, type never enabled' => ['list_identifiers', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'RFC'], 'api/v1/sequence/list', 'type_not_enabled'],
    'resolve, origin unparsable' => ['resolve_project', ['origin' => 'not a repository'], 'api/v1/projects/resolve', 'origin_unparsable'],
]);
