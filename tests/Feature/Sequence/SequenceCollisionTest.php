<?php

use App\Domain\Sequence\Exceptions\SequenceNumberCollision;
use App\Models\Identifier;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;

// FR-004b: under the counter lock a number cannot collide, so a collision means serialization broke — never a silent repeat.
test('a number already in the registry behind the counter is a logged server error', function () {
    Sanctum::actingAs(User::factory()->create());
    Exceptions::fake();

    $pair = enabledPair();
    // Written past SequenceIssuer, so the counter still believes number 1 is free.
    Identifier::factory()->for($pair->project)->for($pair->keyType)->create(['name' => 'written by hand', 'sequence_number' => 1]);

    $this->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'])
        ->assertServerError()
        ->assertJsonMissingPath('sequence_number');

    Exceptions::assertReported(fn (SequenceNumberCollision $e) => $e->context() === [
        'project_key' => 'gitlab.cas.ai/team/backend',
        'type' => 'ADR',
        'sequence_number' => 1,
    ]);
    expect(Identifier::query()->pluck('name_slug')->all())->toBe(['written-by-hand'])
        ->and($pair->refresh()->last_sequence)->toBe(0);
});
