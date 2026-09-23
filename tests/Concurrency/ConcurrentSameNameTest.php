<?php

use App\Models\Identifier;
use Tests\Concurrency\IssueRace;

// SC-002, FR-004a/b: the losers of a same-theme race hit the unique index and must roll their counter increment
// back with it — so the counter moving by exactly one is the assertion that matters, not the shared number.
test('ten simultaneous requests for one theme share one number and burn none', function () {
    $pair = enabledPair(counter: ['seed_sequence' => 7]);

    $issued = collect(IssueRace::run(array_fill(0, 10, ['gitlab.cas.ai/team/backend', 'ADR', 'add-oauth-auth'])));

    expect($issued->pluck('sequence_number')->unique()->all())->toBe([8])
        ->and($issued->where('is_new', true)->count())->toBe(1)
        ->and(Identifier::query()->pluck('sequence_number')->all())->toBe([8])
        ->and($pair->refresh()->last_sequence)->toBe(8);
});
