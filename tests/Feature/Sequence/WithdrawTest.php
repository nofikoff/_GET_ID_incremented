<?php

use App\Domain\Sequence\Exceptions\NotTheLastIdentifier;
use App\Domain\Sequence\SequenceIssuer;
use App\Domain\Sequence\SequenceWithdrawer;
use App\Models\Identifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->issue = fn (string $name): Identifier => Identifier::query()
        ->where('sequence_number', app(SequenceIssuer::class)->issue('gitlab.cas.ai/team/backend', 'ADR', $name)->sequenceNumber)
        ->firstOrFail();
    $this->withdraw = fn (Identifier $identifier) => app(SequenceWithdrawer::class)->withdraw($identifier);
    $this->numbers = fn (): array => Identifier::query()->orderBy('sequence_number')->pluck('sequence_number')->all();
});

test('withdrawing the tail rolls the counter back to the new tail', function () {
    $pair = enabledPair(counter: ['seed_sequence' => 31]);
    ($this->issue)('test-spec');
    $tail = ($this->issue)('test2');

    $withdrawn = ($this->withdraw)($tail);

    expect($withdrawn->formattedId)->toBe('ADR-0033')
        ->and($withdrawn->name)->toBe('test2')
        ->and($withdrawn->sequenceNumber)->toBe(33)
        ->and($withdrawn->lastSequence)->toBe(32)
        ->and($withdrawn->nextSequence)->toBe(33)
        ->and(($this->numbers)())->toBe([32])
        ->and($pair->refresh()->last_sequence)->toBe(32);
});

test('withdrawing the only number returns the pair to its seed', function () {
    $pair = enabledPair(counter: ['seed_sequence' => 31]);

    $withdrawn = ($this->withdraw)(($this->issue)('test-spec'));

    expect($withdrawn->lastSequence)->toBe(0)
        ->and($withdrawn->nextSequence)->toBe(32)
        ->and($pair->refresh()->nextSequence())->toBe(32)
        ->and(($this->numbers)())->toBe([]);
});

test('a seed raised above the issued numbers survives withdrawals', function () {
    $pair = enabledPair();
    foreach (range(1, 10) as $i) {
        ($this->issue)("theme {$i}");
    }
    $pair->update(['seed_sequence' => 50]);
    $raised = ($this->issue)('past the seed');

    expect(($this->withdraw)($raised))->lastSequence->toBe(10)->nextSequence->toBe(51);

    $tenth = Identifier::query()->where('sequence_number', 10)->firstOrFail();
    expect(($this->withdraw)($tenth))->lastSequence->toBe(9)->nextSequence->toBe(51);
});

test('a number with a later one after it is refused and nothing changes', function () {
    $pair = enabledPair();
    ($this->issue)('five');
    $middle = ($this->issue)('six');
    ($this->issue)('seven');

    expect(fn () => ($this->withdraw)($middle))->toThrow(NotTheLastIdentifier::class, 'сейчас это ADR-0003');

    expect(($this->numbers)())->toBe([1, 2, 3])
        ->and($pair->refresh()->last_sequence)->toBe(3);
});

test('a number already withdrawn is not found, not refused as a middle one', function (int $issued) {
    enabledPair();
    foreach (range(1, $issued) as $i) {
        $tail = ($this->issue)("theme {$i}");
    }
    $stale = $tail->replicate();
    $stale->id = $tail->id;
    $stale->exists = true;
    ($this->withdraw)($tail);

    expect(fn () => ($this->withdraw)($stale))->toThrow(ModelNotFoundException::class);
    // range(1, 0) counts down to [1, 0], hence the guard.
    expect(($this->numbers)())->toBe($issued > 1 ? range(1, $issued - 1) : []);
})->with([
    'the pair is left empty' => 1,
    'other numbers remain' => 3,
]);

test('a counter drifted past the registry is realigned by a withdrawal', function () {
    $pair = enabledPair();
    foreach (range(1, 12) as $i) {
        $tail = ($this->issue)("theme {$i}");
    }
    $pair->update(['last_sequence' => 40]);

    expect(($this->withdraw)($tail))->previousLastSequence->toBe(40)->lastSequence->toBe(11)->nextSequence->toBe(12);
});

test('retirement does not stop a withdrawal', function (Closure $retire) {
    $pair = enabledPair();
    ($this->issue)('first');
    $tail = ($this->issue)('second');

    $retire($pair);

    expect(($this->withdraw)($tail))->lastSequence->toBe(1);
})->with([
    'project retired' => fn ($pair) => $pair->project->update(['is_active' => false]),
    'type retired' => fn ($pair) => $pair->keyType->update(['is_active' => false]),
    'pair disabled' => fn ($pair) => $pair->update(['is_enabled' => false]),
]);

test('a withdrawn theme is issued again as a first issuance under the freed number', function () {
    enabledPair();
    ($this->issue)('first');
    ($this->withdraw)(($this->issue)('test2'));

    $again = app(SequenceIssuer::class)->issue('gitlab.cas.ai/team/backend', 'ADR', 'test2');

    expect($again->isNew)->toBeTrue()
        ->and($again->sequenceNumber)->toBe(2);
});
