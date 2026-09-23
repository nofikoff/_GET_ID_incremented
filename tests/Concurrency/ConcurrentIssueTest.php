<?php

use App\Models\Identifier;
use Tests\Concurrency\IssueRace;

// SC-001, principle V: simultaneous connections, each in its own process, racing for one counter row.
test('fifty simultaneous requests for different themes get fifty consecutive numbers', function () {
    $pair = enabledPair();

    $issued = IssueRace::run(array_map(
        fn (int $i): array => ['gitlab.cas.ai/team/backend', 'ADR', "theme {$i}"],
        range(1, 50),
    ));

    expect(collect($issued)->pluck('sequence_number')->sort()->values()->all())->toBe(range(1, 50))
        ->and(collect($issued)->every(fn (array $result) => $result['is_new']))->toBeTrue()
        ->and(Identifier::query()->orderBy('sequence_number')->pluck('sequence_number')->all())->toBe(range(1, 50))
        ->and($pair->refresh()->last_sequence)->toBe(50);
});
