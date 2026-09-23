<?php

use App\Domain\Sequence\SequenceIssuer;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\ProjectKeyType;
use Tests\Concurrency\IssueRace;

// Spec 003 FR-005, SC-003, principle V: a withdrawal racing issuances in its own pair ends as if they ran one after
// another, in some order — numbers consecutive, none twice, the counter on the registry's maximum.
test('a withdrawal racing five issuances leaves the pair consecutive and the counter on its tail', function () {
    $pair = enabledPair();
    foreach (range(1, 10) as $i) {
        app(SequenceIssuer::class)->issue('gitlab.cas.ai/team/backend', 'ADR', "before {$i}");
    }

    $outcomes = IssueRace::race([
        ['getid:withdraw', 'gitlab.cas.ai/team/backend', 'ADR', '10'],
        ...array_map(fn (int $i): array => ['getid:issue', 'gitlab.cas.ai/team/backend', 'ADR', "during {$i}"], range(1, 5)),
    ]);

    $withdrawn = $outcomes[0]['withdrawn'];
    $issuedNumbers = collect(array_slice($outcomes, 1))->pluck('sequence_number')->sort()->values()->all();
    $registry = Identifier::query()->orderBy('sequence_number')->pluck('sequence_number')->all();

    // Withdrawn first: the five reuse 10 upwards. Refused: an issuance got there first and the five follow 10.
    expect($issuedNumbers)->toBe($withdrawn ? range(10, 14) : range(11, 15))
        ->and($registry)->toBe($withdrawn ? range(1, 14) : range(1, 15))
        ->and($pair->refresh()->last_sequence)->toBe(max($registry));
});

// FR-005: the withdrawal holds its own pair's counter row and nothing else, so a neighbouring empty pair keeps issuing.
test('a withdrawal in one pair and first issuances in an empty neighbour both go through', function () {
    $adr = enabledPair();
    $spec = ProjectKeyType::factory()->for($adr->project)
        ->for(KeyType::factory()->state(['code' => 'spec']))
        ->create();
    foreach (range(1, 3) as $i) {
        app(SequenceIssuer::class)->issue('gitlab.cas.ai/team/backend', 'ADR', "adr {$i}");
    }

    $outcomes = IssueRace::race([
        ['getid:withdraw', 'gitlab.cas.ai/team/backend', 'ADR', '3'],
        ...array_map(fn (int $i): array => ['getid:issue', 'gitlab.cas.ai/team/backend', 'spec', "spec {$i}"], range(1, 5)),
    ]);

    expect($outcomes[0])->toMatchArray(['withdrawn' => true, 'last_sequence' => 2])
        ->and(collect(array_slice($outcomes, 1))->pluck('sequence_number')->sort()->values()->all())->toBe(range(1, 5))
        ->and($adr->refresh()->last_sequence)->toBe(2)
        ->and($spec->refresh()->last_sequence)->toBe(5);
});
