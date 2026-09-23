<?php

use App\Models\KeyType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());
});

test('an administrator registers a key type', function () {
    $response = $this->postJson('api/v1/admin/key-types', [
        'code' => 'RFC',
        'name' => 'Request for Comments',
        'format_template' => 'RFC-{number:03d}-{name}',
        'description' => 'Proposals',
    ]);

    $response->assertCreated()->assertExactJson([
        'id' => KeyType::query()->sole()->id,
        'code' => 'RFC',
        'name' => 'Request for Comments',
        'format_template' => 'RFC-{number:03d}-{name}',
        'description' => 'Proposals',
        'is_active' => true,
    ]);
});

// FR-013a: a broken template is refused when saved, not when the first number would be formatted.
test('a template without a number or with an unknown placeholder is refused on save', function (string $template, string $reason) {
    $this->postJson('api/v1/admin/key-types', ['code' => 'RFC', 'name' => 'RFC', 'format_template' => $template])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['format_template' => $reason]);

    expect(KeyType::query()->count())->toBe(0);
})->with([
    'no number' => ['RFC-{name}', 'не содержит номера'],
    'unknown placeholder' => ['RFC-{number}-{date}', 'неизвестный плейсхолдер {date}'],
    'printf-style width' => ['RFC-{number:4d}', 'неизвестный плейсхолдер'],
    'stray brace' => ['RFC-{number}}', 'непарную'],
]);

// Same shape as the project race: the loser's INSERT hits the index after its own validation passed.
test('a duplicate code from a concurrent insert is a validation error, not a crash', function () {
    KeyType::creating(function (KeyType $keyType): void {
        DB::table('key_types')->insert([
            'code' => $keyType->code,
            'name' => 'Racer',
            'format_template' => 'RFC-{number}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->postJson('api/v1/admin/key-types', ['code' => 'RFC', 'name' => 'Request for Comments', 'format_template' => 'RFC-{number:03d}-{name}'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    expect(KeyType::query()->count())->toBe(1);
});

test('a code is registered once, whatever its case', function (string $code) {
    KeyType::factory()->create(['code' => 'ADR']);

    $this->postJson('api/v1/admin/key-types', ['code' => $code, 'name' => 'Again', 'format_template' => 'ADR-{number}'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
})->with(['same' => 'ADR', 'other case' => 'adr']);

test('code, name and template are required and bounded', function (array $input, array $fields) {
    $this->postJson('api/v1/admin/key-types', $input)
        ->assertStatus(422)
        ->assertJsonValidationErrors($fields);
})->with([
    'nothing sent' => [[], ['code', 'name', 'format_template']],
    'too long' => [
        ['code' => str_repeat('C', 33), 'name' => str_repeat('n', 256), 'format_template' => str_repeat('x', 250).'{number}'],
        ['code', 'name', 'format_template'],
    ],
]);

test('an administrator changes a key type and its template is validated again', function () {
    $type = KeyType::factory()->create(['code' => 'ADR', 'format_template' => 'ADR-{number:04d}']);

    $this->patchJson("api/v1/admin/key-types/{$type->id}", ['name' => 'Decision', 'format_template' => 'ADR-{number:05d}'])
        ->assertOk()
        ->assertJsonPath('name', 'Decision')
        ->assertJsonPath('format_template', 'ADR-{number:05d}')
        ->assertJsonPath('code', 'ADR');

    $this->patchJson("api/v1/admin/key-types/{$type->id}", ['format_template' => 'ADR'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['format_template']);

    expect($type->refresh()->format_template)->toBe('ADR-{number:05d}');
});

test('retiring a key type stops issuance in every project', function () {
    $pair = enabledPair();

    $this->patchJson("api/v1/admin/key-types/{$pair->key_type_id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('is_active', false);

    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'type_inactive');
});

test('the list holds every key type, retired ones included', function () {
    KeyType::factory()->create(['code' => 'ADR']);
    KeyType::factory()->inactive()->create(['code' => 'RFC']);

    $this->getJson('api/v1/admin/key-types')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'format_template', 'description', 'is_active']]])
        ->assertJsonPath('data.*.code', ['ADR', 'RFC'])
        ->assertJsonPath('data.*.is_active', [true, false]);
});

test('an administrator gets a not_found for a key type that does not exist', function () {
    $type = KeyType::factory()->create();
    $this->patchJson("api/v1/admin/key-types/{$type->id}", ['name' => 'Real'])->assertOk();

    $this->patchJson('api/v1/admin/key-types/999999', ['name' => 'Ghost'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});
