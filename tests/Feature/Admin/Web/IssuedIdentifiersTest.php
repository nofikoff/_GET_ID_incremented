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
    $this->spec = enabledPair($this->adr->project, ['code' => 'spec']);

    // Zero-padded themes, so one number's theme is never a substring of another's.
    $this->issue = fn (ProjectKeyType $pair, int $count, array $state = []) => Identifier::factory()
        ->count($count)
        ->for($pair->project)
        ->for($pair->keyType)
        ->state(new Sequence(fn (Sequence $sequence): array => [
            'sequence_number' => $sequence->index + 1,
            'name' => sprintf('%s theme %03d', $pair->keyType->code, $sequence->index + 1),
        ]))
        ->create($state);
    $this->card = fn (array $query = []) => $this->get(route('admin.projects.show', [$this->adr->project, ...$query]));
    $this->issuedBlock = fn ($response): string => Str::between($response->getContent(), '<section id="issued">', '</section>');
});

// Spec 004, FR-007: the theme the developer passed to next_id, no formatted name.
test('a pair lists its numbers newest first, each with its type, number, request key, author and date', function () {
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);
    $grace = User::factory()->create(['email' => 'grace@cas.ai']);
    foreach ([[1, 'Add OAuth auth', $ada, '2026-09-20 09:00'], [2, 'Drop legacy API', $grace, '2026-09-21 10:30'], [3, 'Split the monolith', $ada, '2026-09-22 11:45']] as [$number, $name, $author, $at]) {
        $this->travelTo($at);
        Identifier::factory()->for($this->adr->project)->for($this->adr->keyType)->for($author, 'creator')
            ->create(['sequence_number' => $number, 'name' => $name]);
    }

    $block = ($this->issuedBlock)(($this->card)()->assertOk());

    expect($block)
        ->toContain('<tr><th>Тип</th><th>Номер</th><th>Ключ запроса</th><th>Автор</th><th>Выдан</th><th></th></tr>')
        ->not->toContain('Идентификатор')
        ->not->toContain('ADR-000');
    ($this->card)()->assertSeeInOrder([
        'ADR', '3', 'Split the monolith', 'ada@cas.ai', '2026-09-22 11:45',
        'ADR', '2', 'Drop legacy API', 'grace@cas.ai', '2026-09-21 10:30',
        'ADR', '1', 'Add OAuth auth', 'ada@cas.ai', '2026-09-20 09:00',
    ]);
});

test('sixty numbers make two pages of fifty, and paging one pair leaves the other where it is', function () {
    ($this->issue)($this->adr, 60);
    ($this->issue)($this->spec, 60);
    $adrPage = 'page_'.$this->adr->key_type_id;
    $specPage = 'page_'.$this->spec->key_type_id;

    $first = ($this->card)();
    $first->assertOk()
        ->assertSee(['ADR theme 060', 'ADR theme 011', 'spec theme 060', 'spec theme 011'])
        ->assertDontSee(['ADR theme 010', 'spec theme 010'])
        ->assertSee(["{$adrPage}=2", "{$specPage}=2"], false);

    $paged = ($this->card)([$adrPage => 2]);
    $paged->assertOk()
        ->assertSee(['ADR theme 010', 'ADR theme 001', 'spec theme 060', 'spec theme 011'])
        ->assertDontSee(['ADR theme 011', 'spec theme 010'])
        // The other pair's links keep this pair's page too (withQueryString()).
        ->assertSee(["{$adrPage}=2", "{$specPage}=2"], false);
});

// spec-verify S1: PHP rewrites '.' and space in a query-parameter name to '_', so a pageName built from the
// code never paged past 1 for a code containing either (research.md R6).
test('a code with a dot or a space still pages past the first page', function () {
    $rfc = enabledPair($this->adr->project, ['code' => 'RFC.v2']);
    ($this->issue)($rfc, 60);

    ($this->card)(['page_'.$rfc->key_type_id => 2])
        ->assertOk()
        ->assertSee('RFC.v2 theme 010')
        ->assertDontSee('RFC.v2 theme 011');
});

test('the numbers of a disabled pair and of a retired type stay on the card', function () {
    ($this->issue)($this->adr, 2);
    ($this->issue)($this->spec, 1);
    $this->adr->update(['is_enabled' => false]);
    $this->spec->keyType->update(['is_active' => false]);

    expect(($this->issuedBlock)(($this->card)()->assertOk()))
        ->toContain('ADR theme 002')
        ->toContain('ADR theme 001')
        ->toContain('spec theme 001');
});

// Spec 003, FR-001: the tail is the one number that can be removed, and nothing can be edited.
test('the card offers removal of the last number only, and no way to change one', function () {
    $issued = ($this->issue)($this->adr, 3);
    $block = ($this->issuedBlock)(($this->card)()->assertOk());

    expect(substr_count($block, '<form'))->toBe(1)
        ->and($block)->toContain(route('admin.projects.identifiers.destroy', [$this->adr->project, $issued->last()]))
        ->and(preg_match_all('/<input(?![^>]*type="hidden")/', $block))->toBe(0);
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
        ->toContain('ADR theme 001')
        ->toContain('Номеров не выдано');
});
