<?php

use App\Models\KeyType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// FR-011, FR-012: a key type is registered, changed, retired and returned in the browser; its code never changes.

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->store = fn (array $input) => $this->from(route('admin.key-types.create'))->post(route('admin.key-types.store'), $input);
    $this->update = fn (KeyType $keyType, array $input) => $this->from(route('admin.key-types.edit', $keyType))
        ->patch(route('admin.key-types.update', $keyType), $input);
});

test('an administrator registers a key type', function () {
    ($this->store)(['code' => 'RFC', 'name' => 'Request for Comments', 'description' => 'Proposals'])
        ->assertRedirect(route('admin.key-types.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect(KeyType::query()->sole())
        ->code->toBe('RFC')
        ->name->toBe('Request for Comments')
        ->description->toBe('Proposals')
        ->is_active->toBeTrue();
});

// Spec 004: a web form is not a closed body, so a stale template field is dropped rather than refused (data-model.md).
test('a template sent from a stale form is dropped, not stored', function () {
    ($this->store)(['code' => 'RFC', 'name' => 'RFC', 'format_template' => 'RFC-{number}'])
        ->assertRedirect(route('admin.key-types.index'))
        ->assertSessionHasNoErrors();

    expect(KeyType::query()->sole()->getAttributes())->not->toHaveKey('format_template');
});

test('a code already registered in another case returns the form with the error under the code', function () {
    KeyType::factory()->create(['code' => 'ADR']);

    ($this->store)(['code' => 'adr', 'name' => 'Again'])
        ->assertRedirect(route('admin.key-types.create'))
        ->assertSessionHasErrors(['code']);

    expect(KeyType::query()->count())->toBe(1);
});

// Same shape as the project race: the loser's INSERT hits the index after its own validation passed.
test('a duplicate code from a concurrent insert returns the form with the error under the code, not a crash', function () {
    KeyType::creating(function (KeyType $keyType): void {
        DB::table('key_types')->insert([
            'code' => $keyType->code,
            'name' => 'Racer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    ($this->store)(['code' => 'RFC', 'name' => 'Request for Comments'])
        ->assertRedirect(route('admin.key-types.create'))
        ->assertSessionHasErrors(['code']);

    expect(KeyType::query()->sole()->name)->toBe('Racer');
});

test('code and name are required', function () {
    ($this->store)([])->assertSessionHasErrors(['code', 'name']);
});

test('an administrator changes a key type, and its code stays', function () {
    $keyType = KeyType::factory()->create(['code' => 'ADR', 'name' => 'ADR']);

    ($this->update)($keyType, ['name' => 'Decision', 'description' => 'Why', 'code' => 'DEC'])
        ->assertRedirect(route('admin.key-types.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect($keyType->refresh())
        ->code->toBe('ADR')
        ->name->toBe('Decision')
        ->description->toBe('Why');
});

test('a refused change returns the form with the error and changes nothing', function () {
    $keyType = KeyType::factory()->create(['code' => 'ADR', 'name' => 'ADR', 'description' => 'Why']);

    ($this->update)($keyType, ['name' => '', 'description' => 'Changed'])
        ->assertRedirect(route('admin.key-types.edit', $keyType))
        ->assertSessionHasErrors(['name']);

    expect($keyType->refresh())->name->toBe('ADR')->description->toBe('Why');
});

test('retiring a key type and returning it', function () {
    $keyType = KeyType::factory()->create();

    ($this->update)($keyType, ['is_active' => '0'])->assertRedirect(route('admin.key-types.index'))->assertSessionHasNoErrors();
    expect($keyType->refresh()->is_active)->toBeFalse();

    ($this->update)($keyType, ['is_active' => '1'])->assertSessionHasNoErrors();
    expect($keyType->refresh()->is_active)->toBeTrue()
        ->and(KeyType::query()->count())->toBe(1);
});

test('the list holds every key type, retired ones included, and offers a new one', function () {
    $adr = KeyType::factory()->create(['code' => 'ADR', 'name' => 'Architecture Decision Record']);
    KeyType::factory()->inactive()->create(['code' => 'RFC', 'name' => 'Request for Comments']);

    $this->get(route('admin.key-types.index'))
        ->assertOk()
        ->assertSeeInOrder(['ADR', 'Architecture Decision Record', 'действует', 'RFC', 'Request for Comments', 'выведен из обращения'])
        ->assertSee(route('admin.key-types.create'))
        ->assertSee(route('admin.key-types.edit', $adr))
        ->assertDontSee('Шаблон')
        ->assertDontSee('через административный API');
});

// Spec 004, FR-005 / SC-003: nothing in the console suggests the service formats a name.
test('the new key type form carries its fields and its CSRF token, and no template', function () {
    $this->get(route('admin.key-types.create'))
        ->assertOk()
        ->assertSee(route('admin.key-types.store'))
        ->assertSee('name="code"', false)
        ->assertSee('name="name"', false)
        ->assertSee('name="description"', false)
        ->assertSee('name="_token"', false)
        ->assertDontSee('name="format_template"', false)
        ->assertDontSee('Шаблон')
        ->assertDontSee('{number');
});

test('the edit form shows the type, offers neither its code nor a template for change', function () {
    $keyType = KeyType::factory()->create(['code' => 'ADR', 'name' => 'Decision', 'description' => 'Why']);

    $this->get(route('admin.key-types.edit', $keyType))
        ->assertOk()
        ->assertSee('ADR')
        ->assertSee('value="Decision"', false)
        ->assertSee('Why')
        ->assertSee(route('admin.key-types.update', $keyType))
        ->assertDontSee('name="code"', false)
        ->assertDontSee('name="format_template"', false)
        ->assertDontSee('Шаблон')
        ->assertDontSee('действует на номера, выданные после сохранения');
});

// FR-011: retiring stops issuance of the type in every project, so it asks first; returning does not.
test('retiring asks for a confirmation and returning does not', function () {
    $active = KeyType::factory()->create();
    $retired = KeyType::factory()->inactive()->create();

    $this->get(route('admin.key-types.edit', $active))
        ->assertSee('onsubmit="return confirm(', false)
        ->assertSee('name="is_active" value="0"', false);

    $this->get(route('admin.key-types.edit', $retired))
        ->assertDontSee('onsubmit="return confirm(', false)
        ->assertSee('name="is_active" value="1"', false);
});
