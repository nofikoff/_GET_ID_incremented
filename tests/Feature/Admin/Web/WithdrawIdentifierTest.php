<?php

use App\Domain\Sequence\SequenceIssuer;
use App\Models\Identifier;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Support\Str;

// Spec 003: an administrator takes a pair's last number off the project card, one at a time.

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->pair = enabledPair(counter: ['seed_sequence' => 31]);
    $this->issue = fn (string $name, ?ProjectKeyType $pair = null): Identifier => Identifier::query()
        ->where('sequence_number', app(SequenceIssuer::class)->issue(($pair ?? $this->pair)->project->key, ($pair ?? $this->pair)->keyType->code, $name)->sequenceNumber)
        ->whereBelongsTo(($pair ?? $this->pair)->keyType)
        ->sole();
    $this->withdraw = fn (Identifier $identifier, ?Project $project = null) => $this->from(route('admin.projects.show', $this->pair->project))
        ->delete(route('admin.projects.identifiers.destroy', [$project ?? $this->pair->project, $identifier]));
    $this->numbers = fn (): array => Identifier::query()->orderBy('sequence_number')->pluck('sequence_number')->all();
    $this->issuedBlock = fn (): string => Str::between($this->get(route('admin.projects.show', $this->pair->project))->getContent(), '<section id="issued">', '</section>');
});

describe('US1: test numbers come off the tail', function () {
    test('two withdrawals give the first freed number back to the next issuance', function () {
        $first = ($this->issue)('test-spec');
        $second = ($this->issue)('test2');

        ($this->withdraw)($second)
            ->assertRedirect(route('admin.projects.show', $this->pair->project))
            ->assertSessionHas('status', 'Номер ADR 33 удалён. Следующий номер — 33.');
        expect(($this->numbers)())->toBe([32]);

        ($this->withdraw)($first)->assertSessionHas('status', 'Номер ADR 32 удалён. Следующий номер — 32.');
        expect(($this->numbers)())->toBe([])
            ->and($this->pair->refresh()->last_sequence)->toBe(0);

        $again = app(SequenceIssuer::class)->issue('gitlab.cas.ai/team/backend', 'ADR', 'test2');
        expect($again->sequenceNumber)->toBe(32)->and($again->isNew)->toBeTrue();
    });

    test('the removal moves to the new tail, and the confirmation names the number and warns it can return', function () {
        ($this->issue)('test-spec');
        $tail = ($this->issue)('test2');

        expect(($this->issuedBlock)())
            ->toContain(route('admin.projects.identifiers.destroy', [$this->pair->project, $tail]))
            ->toContain('Удалить ADR 33? Номер может быть выдан снова другой теме.');

        ($this->withdraw)($tail);

        expect(($this->issuedBlock)())
            ->toContain('Удалить ADR 32?')
            ->not->toContain('Удалить ADR 33?')
            ->not->toContain('test2');
    });

    test('an empty pair and a later page carry no removal', function () {
        expect(($this->issuedBlock)())->not->toContain('<form');

        foreach (range(1, 51) as $i) {
            ($this->issue)("theme {$i}");
        }
        $secondPage = Str::between(
            $this->get(route('admin.projects.show', [$this->pair->project, 'page_'.$this->pair->key_type_id => 2]))->getContent(),
            '<section id="issued">',
            '</section>',
        );

        expect($secondPage)->toContain('<td>theme 1</td>')->not->toContain('<form');
    });

    test('retirement does not take the removal away', function (Closure $retire) {
        $tail = ($this->issue)('test');
        $retire($this->pair);

        expect(($this->issuedBlock)())->toContain('Удалить ADR 32?');
        ($this->withdraw)($tail)->assertSessionHasNoErrors();
        expect(($this->numbers)())->toBe([]);
    })->with([
        'project retired' => fn (ProjectKeyType $pair) => $pair->project->update(['is_active' => false]),
        'pair disabled' => fn (ProjectKeyType $pair) => $pair->update(['is_enabled' => false]),
        'type retired' => fn (ProjectKeyType $pair) => $pair->keyType->update(['is_active' => false]),
    ]);
});

describe('US2: a number with a later one after it stays', function () {
    test('a middle number is refused with the current tail named, and nothing changes', function () {
        ($this->issue)('five');
        $middle = ($this->issue)('six');
        ($this->issue)('seven');

        ($this->withdraw)($middle)
            ->assertRedirect(route('admin.projects.show', $this->pair->project))
            ->assertSessionHasErrors(["identifiers.{$this->pair->key_type_id}" => 'Удалить можно только последний номер пары: сейчас это ADR 34.']);

        expect(($this->numbers)())->toBe([32, 33, 34])
            ->and($this->pair->refresh()->last_sequence)->toBe(34);
    });

    test('a page gone stale behind a new issuance is refused with the new tail', function () {
        $seen = ($this->issue)('seen');
        ($this->issue)('issued meanwhile');

        ($this->withdraw)($seen)->assertSessionHasErrors(["identifiers.{$this->pair->key_type_id}" => 'Удалить можно только последний номер пары: сейчас это ADR 33.']);
    });

    test('the refusal is shown above the pair on the card', function () {
        $middle = ($this->issue)('six');
        ($this->issue)('seven');

        ($this->withdraw)($middle);

        expect(($this->issuedBlock)())->toContain('сейчас это ADR 33');
    });

    test('a number already withdrawn is not found', function () {
        ($this->issue)('kept');
        $tail = ($this->issue)('gone');
        ($this->withdraw)($tail);

        ($this->withdraw)($tail)->assertNotFound();
        expect(($this->numbers)())->toBe([32]);
    });
});

describe('US4: only an administrator withdraws', function () {
    test('a member gets the same refusal for an existing and a missing number, and nothing is removed', function () {
        $tail = ($this->issue)('test');
        $this->actingAs(User::factory()->create());

        $existing = $this->delete(route('admin.projects.identifiers.destroy', [$this->pair->project, $tail]));
        $missing = $this->delete("/admin/projects/{$this->pair->project_id}/identifiers/999999");

        $existing->assertForbidden();
        $missing->assertForbidden();
        expect($missing->getContent())->toBe($existing->getContent())
            ->and(($this->numbers)())->toBe([32]);
    });

    test('a guest is sent to sign in', function () {
        $tail = ($this->issue)('test');
        auth()->logout();

        $this->delete(route('admin.projects.identifiers.destroy', [$this->pair->project, $tail]))->assertRedirect(route('login'));
        expect(($this->numbers)())->toBe([32]);
    });

    test('a number of another project is not found under this one', function () {
        $other = enabledPair(['repo_url' => 'git@gitlab.cas.ai:team/frontend.git'], $this->pair->keyType);
        $foreign = ($this->issue)('foreign', $other);

        ($this->withdraw)($foreign, $this->pair->project)->assertNotFound();
        expect(Identifier::query()->whereKey($foreign->getKey())->exists())->toBeTrue();
    });
});
