<?php

use App\Models\Identifier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());

    $this->next = fn (string $name) => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => $name],
    )->assertOk();
});

test('issuance continues after the last number taken outside the service', function () {
    $pair = enabledPair(counter: ['seed_sequence' => 42]);

    ($this->next)('add-oauth-auth')
        ->assertJsonPath('sequence_number', 43);
    ($this->next)('drop-oauth-auth')->assertJsonPath('sequence_number', 44);

    expect(Identifier::query()->orderBy('sequence_number')->pluck('sequence_number')->all())->toBe([43, 44])
        ->and($pair->refresh()->last_sequence)->toBe(44);
});

test('a seed raised above the issued numbers moves the next one past it', function () {
    $pair = enabledPair();
    ($this->next)('first');
    ($this->next)('second');

    $pair->update(['seed_sequence' => 10]);

    ($this->next)('third')->assertJsonPath('sequence_number', 11);
});

test('a seed below the issued numbers never rewinds issuance', function () {
    $pair = enabledPair(counter: ['seed_sequence' => 3]);
    ($this->next)('first');
    ($this->next)('second');

    $pair->update(['seed_sequence' => 4]);

    ($this->next)('third')->assertJsonPath('sequence_number', 6);
});
