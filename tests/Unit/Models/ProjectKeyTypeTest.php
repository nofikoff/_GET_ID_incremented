<?php

use App\Models\ProjectKeyType;

test('the next number continues after the larger of the seed and the last issued', function (int $seed, int $last, int $next) {
    $counter = new ProjectKeyType(['seed_sequence' => $seed, 'last_sequence' => $last]);

    expect($counter->nextSequence())->toBe($next);
})->with([
    'fresh pair' => [0, 0, 1],
    'seeded, nothing issued yet' => [42, 0, 43],
    'issued past the seed' => [42, 50, 51],
    'seed raised above the issued' => [10, 5, 11],
]);
