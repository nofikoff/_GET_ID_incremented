<?php

use App\Models\Identifier;
use App\Models\ProjectKeyType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());

    $this->adr = enabledPair();
    $this->next = fn (string $name, string $type = 'ADR') => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => $type, 'name' => $name],
    );
});

test('repeating the same request returns the same number, marked as a repeat', function () {
    $this->travelTo('2026-09-20 14:30:00');
    $first = ($this->next)('add-oauth-auth')->assertOk()->json();

    $this->travelTo('2026-09-21 09:00:00');

    ($this->next)('add-oauth-auth')
        ->assertOk()
        ->assertExactJson([...$first, 'is_new' => false]);

    expect(Identifier::query()->count())->toBe(1)
        ->and($this->adr->refresh()->last_sequence)->toBe(1);
});

// FR-007: the theme is compared by its normalized form, and the answer keeps the first wording.
test('a theme spelled differently is the same theme', function (string $spelling) {
    ($this->next)('add-oauth-auth')->assertOk();

    ($this->next)($spelling)
        ->assertOk()
        ->assertJsonPath('sequence_number', 1)
        ->assertJsonPath('name', 'add-oauth-auth')
        ->assertJsonPath('is_new', false);

    expect(Identifier::query()->count())->toBe(1);
})->with([
    'title case' => 'Add OAuth Auth',
    'underscores' => 'add_oauth_auth',
    'dots' => 'add.oauth.auth',
    'doubled separators and padding' => '  ADD--OAUTH   auth ',
]);

test('the first wording and its formatted id stay with the number', function () {
    enabledPair($this->adr->project, ['code' => 'spec', 'format_template' => '{number:03d}-{name}']);
    ($this->next)('Add OAuth Auth', 'spec')->assertOk();

    ($this->next)('add_oauth_auth', 'spec')
        ->assertJsonPath('name', 'Add OAuth Auth')
        ->assertJsonPath('formatted_id', '001-add-oauth-auth');
});

test('the same theme in another key type is a separate document', function () {
    enabledPair($this->adr->project, ['code' => 'spec', 'format_template' => '{number:03d}-{name}']);
    ($this->next)('add-oauth-auth')->assertOk();

    ($this->next)('add-oauth-auth', 'spec')
        ->assertJsonPath('sequence_number', 1)
        ->assertJsonPath('is_new', true);

    expect(Identifier::query()->count())->toBe(2);
});

// Principle I outranks FR-015 here: a repeat allocates nothing, so retiring the pair does not take the answer away.
test('a repeat still returns its number after the pair is retired, while a new theme is refused', function (Closure $retire, string $code) {
    ($this->next)('add-oauth-auth')->assertOk();

    $retire($this->adr);

    ($this->next)('add-oauth-auth')
        ->assertOk()
        ->assertJsonPath('sequence_number', 1)
        ->assertJsonPath('is_new', false);

    ($this->next)('drop-oauth')
        ->assertStatus(422)
        ->assertJsonPath('error.code', $code);
})->with([
    'project retired' => [fn (ProjectKeyType $pair) => $pair->project->update(['is_active' => false]), 'project_inactive'],
    'type disabled in the project' => [fn (ProjectKeyType $pair) => $pair->update(['is_enabled' => false]), 'type_not_enabled'],
    'type retired' => [fn (ProjectKeyType $pair) => $pair->keyType->update(['is_active' => false]), 'type_inactive'],
]);
