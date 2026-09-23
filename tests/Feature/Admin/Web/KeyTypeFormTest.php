<?php

use App\Models\Identifier;
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
    ($this->store)(['code' => 'RFC', 'name' => 'Request for Comments', 'format_template' => 'RFC-{number:03d}-{name}', 'description' => 'Proposals'])
        ->assertRedirect(route('admin.key-types.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect(KeyType::query()->sole())
        ->code->toBe('RFC')
        ->name->toBe('Request for Comments')
        ->format_template->toBe('RFC-{number:03d}-{name}')
        ->description->toBe('Proposals')
        ->is_active->toBeTrue();
});

// FR-013a of spec 001: a broken template is refused when saved, with IdentifierFormat's own reason under the field.
test('a template without a number or with an unknown placeholder returns the form with the reason under the template', function (string $template, string $reason) {
    ($this->store)(['code' => 'RFC', 'name' => 'RFC', 'format_template' => $template])
        ->assertRedirect(route('admin.key-types.create'))
        ->assertSessionHasErrors(['format_template' => $reason])
        ->assertSessionHasInput(['code' => 'RFC', 'format_template' => $template]);

    expect(KeyType::query()->count())->toBe(0);
})->with([
    'no number' => ['RFC-{name}', 'Шаблон «RFC-{name}» не содержит номера: нужен {number} или {number:0Nd}.'],
    'unknown placeholder' => ['RFC-{number}-{date}', 'Шаблон «RFC-{number}-{date}» содержит неизвестный плейсхолдер {date}: допустимы {number}, {number:0Nd} и {name}.'],
]);

test('a code already registered in another case returns the form with the error under the code', function () {
    KeyType::factory()->create(['code' => 'ADR']);

    ($this->store)(['code' => 'adr', 'name' => 'Again', 'format_template' => 'ADR-{number}'])
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
            'format_template' => 'RFC-{number}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    ($this->store)(['code' => 'RFC', 'name' => 'Request for Comments', 'format_template' => 'RFC-{number:03d}'])
        ->assertRedirect(route('admin.key-types.create'))
        ->assertSessionHasErrors(['code']);

    expect(KeyType::query()->sole()->name)->toBe('Racer');
});

test('code, name and template are required', function () {
    ($this->store)([])->assertSessionHasErrors(['code', 'name', 'format_template']);
});

test('an administrator changes a key type, and its code stays', function () {
    $keyType = KeyType::factory()->create(['code' => 'ADR', 'name' => 'ADR', 'format_template' => 'ADR-{number:04d}']);

    ($this->update)($keyType, ['name' => 'Decision', 'format_template' => 'ADR-{number:05d}', 'description' => 'Why', 'code' => 'DEC'])
        ->assertRedirect(route('admin.key-types.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect($keyType->refresh())
        ->code->toBe('ADR')
        ->name->toBe('Decision')
        ->format_template->toBe('ADR-{number:05d}')
        ->description->toBe('Why');
});

test('a refused change returns the form with the error and changes nothing', function () {
    $keyType = KeyType::factory()->create(['code' => 'ADR', 'name' => 'ADR', 'format_template' => 'ADR-{number:04d}']);

    ($this->update)($keyType, ['name' => '', 'format_template' => 'ADR'])
        ->assertRedirect(route('admin.key-types.edit', $keyType))
        ->assertSessionHasErrors(['name', 'format_template']);

    expect($keyType->refresh())->name->toBe('ADR')->format_template->toBe('ADR-{number:04d}');
});

// FR-012: formatted_id is fixed at issuance, so a new template reaches only the numbers issued after it.
test('a new template leaves the issued numbers as they were issued', function () {
    $pair = enabledPair();
    $issued = Identifier::factory()->for($pair->project)->for($pair->keyType)->create(['sequence_number' => 7]);

    ($this->update)($pair->keyType, ['name' => 'ADR', 'format_template' => 'ADR-{number:03d}'])->assertSessionHasNoErrors();

    expect($issued->refresh()->formatted_id)->toBe('ADR-0007')
        ->and($pair->keyType->refresh()->format_template)->toBe('ADR-{number:03d}');
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
    $adr = KeyType::factory()->create(['code' => 'ADR', 'format_template' => 'ADR-{number:04d}']);
    KeyType::factory()->inactive()->create(['code' => 'RFC', 'format_template' => 'RFC-{number}']);

    $this->get(route('admin.key-types.index'))
        ->assertOk()
        ->assertSeeInOrder(['ADR', 'ADR-{number:04d}', 'действует', 'RFC', 'RFC-{number}', 'выведен из обращения'])
        ->assertSee(route('admin.key-types.create'))
        ->assertSee(route('admin.key-types.edit', $adr))
        ->assertDontSee('через административный API');
});

test('the new key type form carries its fields and its CSRF token', function () {
    $this->get(route('admin.key-types.create'))
        ->assertOk()
        ->assertSee(route('admin.key-types.store'))
        ->assertSee('name="code"', false)
        ->assertSee('name="name"', false)
        ->assertSee('name="format_template"', false)
        ->assertSee('name="description"', false)
        ->assertSee('name="_token"', false);
});

test('the edit form shows the type and does not offer its code for change', function () {
    $keyType = KeyType::factory()->create(['code' => 'ADR', 'name' => 'Decision', 'format_template' => 'ADR-{number:04d}', 'description' => 'Why']);

    $this->get(route('admin.key-types.edit', $keyType))
        ->assertOk()
        ->assertSee('ADR')
        ->assertSee('value="Decision"', false)
        ->assertSee('value="ADR-{number:04d}"', false)
        ->assertSee('Why')
        ->assertSee(route('admin.key-types.update', $keyType))
        ->assertDontSee('name="code"', false);
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
