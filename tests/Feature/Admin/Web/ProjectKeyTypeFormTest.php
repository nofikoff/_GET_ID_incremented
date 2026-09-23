<?php

use App\Http\Requests\Web\Admin\SetProjectKeyTypesRequest;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;

// FR-006–FR-010: the project card sets the enabled types and their seeds in one submission.

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git']);
    $this->adr = KeyType::factory()->create(['code' => 'ADR', 'name' => 'Architecture Decision Record', 'format_template' => 'ADR-{number:04d}']);
    $this->spec = KeyType::factory()->create(['code' => 'spec', 'name' => 'Specification', 'format_template' => '{number:03d}-{name}']);

    $this->card = fn () => $this->get(route('admin.projects.show', $this->project));
    $this->submit = fn (array $types) => $this->from(route('admin.projects.show', $this->project))
        ->put(route('admin.projects.key-types.update', $this->project), ['types' => $types]);
    $this->pair = fn (KeyType $type) => ProjectKeyType::query()
        ->where('project_id', $this->project->id)
        ->where('key_type_id', $type->id)
        ->first();
});

test('an administrator enables ADR seeded at 12 and spec without a seed, and the card shows the next numbers', function () {
    ($this->submit)(['ADR' => ['enabled' => '1', 'seed_sequence' => '12'], 'spec' => ['enabled' => '1', 'seed_sequence' => '']])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Набор типов проекта сохранён.');

    expect(($this->pair)($this->adr))->is_enabled->toBeTrue()->seed_sequence->toBe(12)
        ->and(($this->pair)($this->adr)->nextSequence())->toBe(13)
        ->and(($this->pair)($this->spec))->is_enabled->toBeTrue()->seed_sequence->toBe(0)
        ->and(($this->pair)($this->spec)->nextSequence())->toBe(1);

    ($this->card)()->assertOk()->assertSeeInOrder([
        'ADR', 'Architecture Decision Record', '12', '0', '13',
        'spec', 'Specification', '0', '0', '1',
    ]);
});

test('an empty seed field keeps the current seed', function () {
    enabledPair($this->project, $this->adr, ['seed_sequence' => 42]);

    ($this->submit)(['ADR' => ['enabled' => '1', 'seed_sequence' => '']])->assertSessionHasNoErrors();

    expect(($this->pair)($this->adr))->seed_sequence->toBe(42)->is_enabled->toBeTrue();
});

// FR-008: the error lands under the seed field of that type, and the whole set stays as it was.
test('a seed below the last issued number is refused under that type\'s seed, and nothing changes', function () {
    enabledPair($this->project, $this->adr, ['last_sequence' => 3]);
    enabledPair($this->project, $this->spec);

    ($this->submit)(['ADR' => ['enabled' => '1', 'seed_sequence' => '2'], 'spec' => ['enabled' => '1', 'seed_sequence' => '5']])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHasErrors(['types.ADR.seed_sequence' => 'Начальный номер 2 ниже уже выданного номера 3 по типу «ADR» в этом проекте.'])
        ->assertSessionHasInput('types.ADR.seed_sequence', '2');

    expect(($this->pair)($this->adr))->seed_sequence->toBe(0)->is_enabled->toBeTrue()
        ->and(($this->pair)($this->spec))->seed_sequence->toBe(0);
});

// FR-009: the counter lives on the link row, so an unchecked type is disabled and its row kept.
test('an unchecked type is disabled with its counter kept', function () {
    enabledPair($this->project, $this->adr, ['last_sequence' => 7]);

    ($this->submit)(['ADR' => ['seed_sequence' => ''], 'spec' => ['enabled' => '1', 'seed_sequence' => '']])->assertSessionHasNoErrors();

    expect(($this->pair)($this->adr))->not->toBeNull()->is_enabled->toBeFalse()->last_sequence->toBe(7)
        ->and(ProjectKeyType::query()->where('project_id', $this->project->id)->count())->toBe(2);
});

test('unchecking every type disables them all', function () {
    enabledPair($this->project, $this->adr);

    ($this->submit)(['ADR' => ['seed_sequence' => ''], 'spec' => ['seed_sequence' => '']])->assertSessionHasNoErrors();

    expect(($this->pair)($this->adr)->is_enabled)->toBeFalse();
});

// FR-010: the checkbox of a type retired while the form was open still arrives, and is refused where it stands.
test('a retired type cannot be enabled, and the error is under its checkbox', function () {
    $this->adr->update(['is_active' => false]);

    ($this->submit)(['ADR' => ['enabled' => '1', 'seed_sequence' => ''], 'spec' => ['enabled' => '1', 'seed_sequence' => '']])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHasErrors(['types.ADR.enabled' => 'Тип «ADR» выведен из обращения и не может быть включён.']);

    expect(ProjectKeyType::query()->count())->toBe(0);
});

test('a type retired after the form passed validation is refused the same way, and nothing changes', function () {
    // The container validates a FormRequest in its own afterResolving hook, registered before this one.
    $this->app->afterResolving(SetProjectKeyTypesRequest::class, fn () => $this->adr->update(['is_active' => false]));

    ($this->submit)(['spec' => ['enabled' => '1', 'seed_sequence' => ''], 'ADR' => ['enabled' => '1', 'seed_sequence' => '']])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHasErrors(['types.ADR.enabled' => 'Тип «ADR» выведен из обращения и не может быть включён.']);

    expect(ProjectKeyType::query()->count())->toBe(0);
});

// Edge Cases: a retired type enabled in the project before its retirement shows without a checkbox and goes on save.
test('an enabled pair of a retired type is marked on the card and disabled by saving', function () {
    enabledPair($this->project, $this->adr, ['last_sequence' => 4]);
    $this->adr->update(['is_active' => false]);

    ($this->card)()
        ->assertOk()
        ->assertSee('выведен из обращения, будет выключен при сохранении')
        ->assertDontSee('name="types[ADR][enabled]"', false);

    ($this->submit)(['spec' => ['enabled' => '1', 'seed_sequence' => '']])->assertSessionHasNoErrors();

    expect(($this->pair)($this->adr))->is_enabled->toBeFalse()->last_sequence->toBe(4);
});

test('the form lists every active type and checks the enabled ones', function () {
    enabledPair($this->project, $this->adr);
    KeyType::factory()->inactive()->create(['code' => 'RFC']);

    ($this->card)()
        ->assertOk()
        ->assertSee('name="types[ADR][enabled]" value="1" checked', false)
        ->assertSee('name="types[spec][enabled]" value="1"', false)
        ->assertDontSee('name="types[spec][enabled]" value="1" checked', false)
        ->assertSee('name="types[spec][seed_sequence]"', false)
        ->assertDontSee('types[RFC]', false);
});

// FR-006: a brand-new project has nothing to compare against, so the form offers every active type ready to enable.
test('a brand-new project with no pairs at all pre-checks every active type, unenabled', function () {
    ($this->card)()
        ->assertOk()
        ->assertSee('name="types[ADR][enabled]" value="1" checked', false)
        ->assertSee('name="types[spec][enabled]" value="1" checked', false)
        // None of them is an actually enabled pair, so unticking one is not a "dropped" type the confirm script warns about.
        ->assertDontSee('data-enabled-code="', false);
});

test('a project with an existing pair shows its real state instead of pre-checking every type', function () {
    enabledPair($this->project, $this->adr, ['is_enabled' => false]);

    ($this->card)()
        ->assertOk()
        ->assertDontSee('name="types[ADR][enabled]" value="1" checked', false)
        ->assertDontSee('name="types[spec][enabled]" value="1" checked', false);
});

test('a card with no active type to offer points to the key type registry instead of an empty form', function () {
    KeyType::query()->update(['is_active' => false]);

    ($this->card)()
        ->assertOk()
        ->assertSee('Действующих типов нет')
        ->assertDontSee(route('admin.projects.key-types.update', $this->project));
});

test('a refused set shows the form as it was sent', function () {
    enabledPair($this->project, $this->adr, ['last_sequence' => 3]);

    ($this->submit)(['ADR' => ['seed_sequence' => '2'], 'spec' => ['enabled' => '1', 'seed_sequence' => 'many']])
        ->assertSessionHasErrors(['types.spec.seed_sequence']);

    ($this->card)()
        ->assertSee('name="types[spec][enabled]" value="1" checked', false)
        ->assertDontSee('name="types[ADR][enabled]" value="1" checked', false)
        ->assertSee('value="many"', false);
});

test('the set is validated under the field of each type', function (array $types, array $fields) {
    ($this->submit)($types)->assertSessionHasErrors($fields);

    expect(ProjectKeyType::query()->count())->toBe(0);
})->with([
    'unknown code' => [['RFC' => ['enabled' => '1']], ['types.RFC.enabled']],
    'negative seed' => [['ADR' => ['enabled' => '1', 'seed_sequence' => '-1']], ['types.ADR.seed_sequence']],
    'seed not a number' => [['ADR' => ['enabled' => '1', 'seed_sequence' => 'many']], ['types.ADR.seed_sequence']],
]);

// Production bug: a seed typed under an unchecked box was silently dropped, and the admin saw a save that did nothing.
test('a seed on an unchecked type is refused instead of silently dropped', function () {
    ($this->submit)(['ADR' => ['seed_sequence' => '175']])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHasErrors(['types.ADR.seed_sequence' => 'Отметьте тип, чтобы задать начальный номер.'])
        ->assertSessionHasInput('types.ADR.seed_sequence', '175');

    expect(ProjectKeyType::query()->count())->toBe(0);
});

test('a seed on an unchecked type of a project that already has other pairs is refused the same way', function () {
    enabledPair($this->project, $this->spec, ['seed_sequence' => 5]);

    ($this->submit)(['spec' => ['enabled' => '1', 'seed_sequence' => ''], 'ADR' => ['seed_sequence' => '9']])
        ->assertSessionHasErrors(['types.ADR.seed_sequence' => 'Отметьте тип, чтобы задать начальный номер.']);

    expect(($this->pair)($this->adr))->toBeNull()
        ->and(($this->pair)($this->spec))->seed_sequence->toBe(5)->is_enabled->toBeTrue();
});

// The set derives "changed" from the same before/after comparison the trace already runs (RegistryChangeLog::keyTypesSet).
test('resubmitting the exact same set flashes that nothing changed', function () {
    enabledPair($this->project, $this->adr, ['seed_sequence' => 12]);

    ($this->submit)(['ADR' => ['enabled' => '1', 'seed_sequence' => '12']])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Изменений нет.');
});

test('a submission with nothing to enable and no pairs yet flashes that nothing changed', function () {
    ($this->submit)([])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Изменений нет.');

    expect(ProjectKeyType::query()->count())->toBe(0);
});

// FR-009: dropping an enabled type stops its issuance, so the form lists what it drops and asks first.
test('the form marks the enabled pairs its confirmation lists', function () {
    enabledPair($this->project, $this->adr);
    $retired = KeyType::factory()->create(['code' => 'OLD']);
    enabledPair($this->project, $retired);
    $retired->update(['is_active' => false]);

    ($this->card)()
        ->assertSee('data-enabled-code="ADR"', false)
        ->assertSee('data-enabled-code="OLD"', false)
        ->assertDontSee('data-enabled-code="spec"', false)
        ->assertSee('confirm(', false);
});
