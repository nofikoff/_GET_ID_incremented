<?php

use App\Models\User;

beforeEach(function () {
    $this->withToken(User::factory()->create()->createToken('laptop')->plainTextToken);
});

test('the server introduces itself and tells the model to resolve the project first', function () {
    $result = $this->mcp('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->json('result');

    expect($result['serverInfo']['name'])->toBe('get-id')
        ->and($result['instructions'])->toContain('resolve_project')->toContain('git remote get-url origin');
});

// Spec 004, FR-004: the instructions reach every connected assistant, so they carry the naming convention too.
test('the instructions say only the number is issued and the repository builds the three-digit name', function () {
    $instructions = $this->mcp('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->json('result.instructions');

    expect($instructions)
        ->toContain('Сервис выдаёт только номер, имя документа собирает репозиторий')
        ->toContain('дополняется нулями до трёх цифр')
        ->toContain('`docs/adr/adr-043-<slug>.md`, `specs/043-<slug>/`')
        ->toContain('Для Spec Kit передайте номер явно')
        ->toContain('Повтор с той же темой безопасен');
});

// FR-024: administration is left out of the MCP surface on purpose; the registry grows by a person's decision.
test('the server offers exactly the three registry tools', function () {
    $names = collect($this->mcp('tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['list_identifiers', 'next_id', 'resolve_project']);
});
