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
        'description' => 'Proposals',
    ]);

    // Spec 004, FR-006: exact shape, so the template coming back fails here.
    $response->assertCreated()->assertExactJson([
        'id' => KeyType::query()->sole()->id,
        'code' => 'RFC',
        'name' => 'Request for Comments',
        'description' => 'Proposals',
        'is_active' => true,
    ]);
});

// Same shape as the project race: the loser's INSERT hits the index after its own validation passed.
test('a duplicate code from a concurrent insert is a validation error, not a crash', function () {
    KeyType::creating(function (KeyType $keyType): void {
        DB::table('key_types')->insert([
            'code' => $keyType->code,
            'name' => 'Racer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->postJson('api/v1/admin/key-types', ['code' => 'RFC', 'name' => 'Request for Comments'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    expect(KeyType::query()->count())->toBe(1);
});

test('a code is registered once, whatever its case', function (string $code) {
    KeyType::factory()->create(['code' => 'ADR']);

    $this->postJson('api/v1/admin/key-types', ['code' => $code, 'name' => 'Again'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
})->with(['same' => 'ADR', 'other case' => 'adr']);

test('code and name are required and bounded', function (array $input, array $fields) {
    $this->postJson('api/v1/admin/key-types', $input)
        ->assertStatus(422)
        ->assertJsonValidationErrors($fields);
})->with([
    'nothing sent' => [[], ['code', 'name']],
    'too long' => [
        ['code' => str_repeat('C', 33), 'name' => str_repeat('n', 256)],
        ['code', 'name'],
    ],
]);

test('an administrator changes a key type and its name is validated again', function () {
    $type = KeyType::factory()->create(['code' => 'ADR', 'name' => 'ADR']);

    $this->patchJson("api/v1/admin/key-types/{$type->id}", ['name' => 'Decision', 'description' => 'Why'])
        ->assertOk()
        ->assertJsonPath('name', 'Decision')
        ->assertJsonPath('description', 'Why')
        ->assertJsonPath('code', 'ADR');

    $this->patchJson("api/v1/admin/key-types/{$type->id}", ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect($type->refresh()->name)->toBe('Decision');
});

// Spec 004, FR-005: a type registered without a template issues numbers.
test('a key type registered without a template issues numbers once enabled', function () {
    $this->postJson('api/v1/admin/key-types', ['code' => 'RFC', 'name' => 'Request for Comments'])->assertCreated();
    $pair = enabledPair(keyType: KeyType::query()->where('code', 'RFC')->sole());

    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'RFC', 'name' => 'first'])
        ->assertOk()
        ->assertJsonPath('sequence_number', 1);

    expect($pair->refresh()->last_sequence)->toBe(1);
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
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'description', 'is_active']]])
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
