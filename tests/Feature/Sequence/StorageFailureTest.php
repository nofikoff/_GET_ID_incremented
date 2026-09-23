<?php

use App\Models\Identifier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    Exceptions::fake();

    $this->adr = enabledPair();
    $this->next = fn (string $name) => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => $name],
    );

    // Fails the matching statement at the connection, however the issuer phrases it; cleared to model recovery.
    $this->failing = null;
    DB::connection()->beforeExecuting(function (string $query, array $bindings) {
        if ($this->failing !== null && preg_match($this->failing, $query) === 1) {
            throw new QueryException('mysql', $query, $bindings, new PDOException('SQLSTATE[HY000]: General error: 1021 Disk full; waiting for someone to free some space'));
        }
    });
});

test('a storage failure mid-issuance gives the client an error, not a number, and moves nothing', function (string $statement) {
    ($this->next)('init-project')->assertOk();

    $this->failing = $statement;

    ($this->next)('add-oauth-auth')
        ->assertServerError()
        ->assertJsonMissingPath('sequence_number');

    Exceptions::assertReported(QueryException::class);
    expect(Identifier::query()->pluck('name_slug')->all())->toBe(['init-project'])
        ->and($this->adr->refresh()->last_sequence)->toBe(1);

    $this->failing = null;

    // FR-004c: the retry after recovery is safe, and the failed attempt left no gap behind it.
    ($this->next)('add-oauth-auth')
        ->assertOk()
        ->assertJsonPath('sequence_number', 2)
        ->assertJsonPath('is_new', true);
    expect($this->adr->refresh()->last_sequence)->toBe(2);
})->with([
    'counter write fails' => '/^update `project_key_type`/',
    'registry insert fails' => '/^insert into `identifiers`/',
]);
