<?php

use App\Models\Identifier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// FR-005: an id is fixed when its number is issued; a template edit reaches only the numbers issued after it.

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->pair = enabledPair();
    $this->next = fn (string $name) => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => $name],
    )->assertOk();
});

test('the registry keeps the id each number was issued under', function () {
    ($this->next)('Add OAuth Auth');

    expect(Identifier::query()->sole()->formatted_id)->toBe('ADR-0001');
});

test('a template edit leaves issued ids as they were, in repeats and in the list, and shapes the next issuance', function () {
    ($this->next)('add-oauth-auth');

    $this->patchJson("api/v1/admin/key-types/{$this->pair->key_type_id}", ['format_template' => 'DEC-{number:03d}-{name}'])
        ->assertOk();

    ($this->next)('Add OAuth Auth')
        ->assertJsonPath('formatted_id', 'ADR-0001')
        ->assertJsonPath('is_new', false);
    ($this->next)('drop-oauth')
        ->assertJsonPath('formatted_id', 'DEC-002-drop-oauth')
        ->assertJsonPath('is_new', true);

    $this->getJson('api/v1/sequence/list?'.http_build_query(['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR']))
        ->assertOk()
        ->assertJsonPath('items.*.formatted_id', ['DEC-002-drop-oauth', 'ADR-0001']);
});
