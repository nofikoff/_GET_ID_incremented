<?php

use App\Mcp\Servers\GetIdServer;
use App\Mcp\Tools\ResolveProjectTool;
use App\Models\KeyType;
use App\Models\User;

beforeEach(function () {
    $this->resolve = fn (string $origin) => GetIdServer::actingAs(User::factory()->create())
        ->tool(ResolveProjectTool::class, ['origin' => $origin]);
});

// FR-023: without this the assistant guesses the key from the directory name, plausibly and wrongly.
test('the description tells the model the client reads origin itself', function () {
    $tool = (new ResolveProjectTool)->toArray();

    expect($tool['name'])->toBe('resolve_project')
        ->and($tool['description'])
        ->toContain('Вызывайте это первым')
        ->toContain('git remote get-url origin')
        ->toContain('сервер к вашему репозиторию доступа не имеет')
        ->and($tool['inputSchema']['required'])->toBe(['origin'])
        ->and($tool['inputSchema']['properties']['origin']['type'])->toBe('string')
        ->and($tool['inputSchema']['properties']['origin']['description'])->toContain('git remote get-url origin');
});

test('a registered origin resolves to its key and the types enabled in it', function (string $origin) {
    $pair = enabledPair(['name' => 'Backend'], ['name' => 'Architecture Decision Record'], ['seed_sequence' => 12]);
    enabledPair($pair->project, ['code' => 'spec', 'name' => 'Specification'], ['last_sequence' => 3]);
    enabledPair($pair->project, ['code' => 'RFC'], ['is_enabled' => false]);

    ($this->resolve)($origin)->assertOk()->assertStructuredContent([
        'project_key' => 'gitlab.cas.ai/team/backend',
        'registered' => true,
        'active' => true,
        'name' => 'Backend',
        'types' => [
            ['code' => 'ADR', 'name' => 'Architecture Decision Record', 'next_number' => 13],
            ['code' => 'spec', 'name' => 'Specification', 'next_number' => 4],
        ],
        'hint' => null,
    ]);
})->with([
    'ssh' => 'git@gitlab.cas.ai:team/backend.git',
    'https' => 'https://gitlab.cas.ai/team/backend',
]);

// An unregistered project is an ordinary answer, not a tool error (contracts/mcp-tools.md).
test('an unregistered origin answers registered false with a hint for the person, and reveals no other project', function () {
    enabledPair();
    KeyType::factory()->create(['code' => 'spec']);

    ($this->resolve)('git@gitlab.cas.ai:team/sandbox.git')
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('project_key', 'gitlab.cas.ai/team/sandbox')
                ->where('registered', false)
                ->where('active', false)
                ->where('name', null)
                ->where('types', [])
                ->where('hint', fn (string $hint) => str_contains($hint, 'gitlab.cas.ai/team/sandbox') && str_contains($hint, 'администратора'));
        })
        ->assertDontSee('gitlab.cas.ai/team/backend');
});

test('an origin that is not a repository address is a tool error led by its REST code', function () {
    ($this->resolve)('not a repository')->assertHasErrors(['origin_unparsable: ']);
});

test('an origin is required', function () {
    GetIdServer::actingAs(User::factory()->create())->tool(ResolveProjectTool::class)->assertHasErrors(['origin']);
});
