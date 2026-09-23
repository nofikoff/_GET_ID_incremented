<?php

use App\Models\Identifier;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// FR-013: the project card lists what each pair issued, newest first, 50 a page per pair, and changes none of it.

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->adr = enabledPair();
    $this->spec = enabledPair($this->adr->project, ['code' => 'spec', 'format_template' => 'SPEC-{number:03d}']);

    $this->issue = fn (ProjectKeyType $pair, int $count, array $state = []) => Identifier::factory()
        ->count($count)
        ->for($pair->project)
        ->for($pair->keyType)
        ->state(new Sequence(fn (Sequence $sequence): array => ['sequence_number' => $sequence->index + 1]))
        ->create($state);
    $this->card = fn (array $query = []) => $this->get(route('admin.projects.show', [$this->adr->project, ...$query]));
    $this->issuedBlock = fn ($response): string => Str::between($response->getContent(), '<section id="issued">', '</section>');
});

test('a pair lists its numbers newest first, each with its identifier, first wording, author and date', function () {
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);
    $grace = User::factory()->create(['email' => 'grace@cas.ai']);
    foreach ([[1, 'Add OAuth auth', $ada, '2026-09-20 09:00'], [2, 'Drop legacy API', $grace, '2026-09-21 10:30'], [3, 'Split the monolith', $ada, '2026-09-22 11:45']] as [$number, $name, $author, $at]) {
        $this->travelTo($at);
        Identifier::factory()->for($this->adr->project)->for($this->adr->keyType)->for($author, 'creator')
            ->create(['sequence_number' => $number, 'name' => $name]);
    }

    ($this->card)()->assertOk()->assertSeeInOrder([
        'ADR-0003', 'Split the monolith', 'ada@cas.ai', '2026-09-22 11:45',
        'ADR-0002', 'Drop legacy API', 'grace@cas.ai', '2026-09-21 10:30',
        'ADR-0001', 'Add OAuth auth', 'ada@cas.ai', '2026-09-20 09:00',
    ]);
});

test('sixty numbers make two pages of fifty, and paging one pair leaves the other where it is', function () {
    ($this->issue)($this->adr, 60);
    ($this->issue)($this->spec, 60);

    $first = ($this->card)();
    $first->assertOk()
        ->assertSee(['ADR-0060', 'ADR-0011', 'SPEC-060', 'SPEC-011'])
        ->assertDontSee(['ADR-0010', 'SPEC-010'])
        ->assertSee('page_ADR=2', false)
        ->assertSee('page_spec=2', false);

    $paged = ($this->card)(['page_ADR' => 2]);
    $paged->assertOk()
        ->assertSee(['ADR-0010', 'ADR-0001', 'SPEC-060', 'SPEC-011'])
        ->assertDontSee(['ADR-0011', 'SPEC-010'])
        // The other pair's links keep this pair's page.
        ->assertSee('page_ADR=2&amp;page_spec=2', false);
});

test('the numbers of a disabled pair and of a retired type stay on the card', function () {
    ($this->issue)($this->adr, 2);
    ($this->issue)($this->spec, 1);
    $this->adr->update(['is_enabled' => false]);
    $this->spec->keyType->update(['is_active' => false]);

    expect(($this->issuedBlock)(($this->card)()->assertOk()))
        ->toContain('ADR-0002')
        ->toContain('ADR-0001')
        ->toContain('SPEC-001');
});

test('the card offers no way to change or remove an issued number', function () {
    ($this->issue)($this->adr, 3);

    expect(($this->issuedBlock)(($this->card)()->assertOk()))
        ->toContain('ADR-0003')
        ->not->toContain('<form')
        ->not->toContain('<input');
});

test('the card makes as many queries for fifty numbers a pair as for one', function () {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        ($this->card)()->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    ($this->issue)($this->adr, 1);
    ($this->issue)($this->spec, 1);
    $withOne = $queries();

    Identifier::factory()->count(49)->for($this->adr->project)->for($this->adr->keyType)
        ->state(new Sequence(fn (Sequence $sequence): array => ['sequence_number' => $sequence->index + 2]))
        ->create();

    expect($queries())->toBe($withOne);
});

test('a pair that has issued nothing says so', function () {
    ($this->issue)($this->adr, 1);

    expect(($this->issuedBlock)(($this->card)()->assertOk()))
        ->toContain('ADR-0001')
        ->toContain('Номеров не выдано');
});
