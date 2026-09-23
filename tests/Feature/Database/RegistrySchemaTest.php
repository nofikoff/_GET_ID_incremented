<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $now = now();

    $this->userId = DB::table('users')->insertGetId([
        'name' => 'Ada', 'email' => 'ada@cas.ai', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->projectId = DB::table('projects')->insertGetId([
        'key' => 'gitlab.cas.ai/team/backend', 'name' => 'Backend',
        'repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'created_by' => $this->userId,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->keyTypeId = DB::table('key_types')->insertGetId([
        'code' => 'ADR', 'name' => 'Architecture Decision Record',
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $this->issue = function (string $slug, int $number, ?int $keyTypeId = null): void {
        DB::table('identifiers')->insert([
            'project_id' => $this->projectId, 'key_type_id' => $keyTypeId ?? $this->keyTypeId,
            'name' => $slug, 'name_slug' => $slug, 'sequence_number' => $number,
            'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
});

test('users carry google identity, role and deactivation instead of a password', function () {
    expect(Schema::hasColumns('users', ['google_id', 'avatar_url', 'role', 'deactivated_at']))->toBeTrue()
        ->and(Schema::hasColumn('users', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(DB::table('users')->where('id', $this->userId)->value('role'))->toBe('member');
});

test('users.role accepts only member and admin', function () {
    DB::table('users')->where('id', $this->userId)->update(['role' => 'owner']);
})->throws(QueryException::class);

test('project keys are unique', function () {
    DB::table('projects')->insert([
        'key' => 'gitlab.cas.ai/team/backend', 'name' => 'Fork', 'repo_url' => 'https://gitlab.cas.ai/team/backend',
        'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
})->throws(UniqueConstraintViolationException::class);

test('a project and key type pair has one counter row, starting at zero and enabled', function () {
    $pair = ['project_id' => $this->projectId, 'key_type_id' => $this->keyTypeId];
    DB::table('project_key_type')->insert($pair);

    expect((array) DB::table('project_key_type')->where($pair)->first(['seed_sequence', 'last_sequence', 'is_enabled']))
        ->toEqual(['seed_sequence' => 0, 'last_sequence' => 0, 'is_enabled' => 1]);

    DB::table('project_key_type')->insert($pair);
})->throws(UniqueConstraintViolationException::class);

test('a document name is issued once per project and key type', function () {
    ($this->issue)('add-oauth', 1);
    ($this->issue)('add-oauth', 2);
})->throws(UniqueConstraintViolationException::class);

test('a number is issued once per project and key type', function () {
    ($this->issue)('add-oauth', 1);
    ($this->issue)('drop-oauth', 1);
})->throws(UniqueConstraintViolationException::class);

// Spec 004 FR-009: the document name is the consumer's convention, so the registry keeps no trace of it.
test('neither a key type template nor a formatted id is stored', function () {
    expect(Schema::hasColumn('key_types', 'format_template'))->toBeFalse()
        ->and(Schema::hasColumn('identifiers', 'formatted_id'))->toBeFalse();
});

test('the same name and number are free in another key type', function () {
    $specTypeId = DB::table('key_types')->insertGetId([
        'code' => 'spec', 'name' => 'Specification',
    ]);

    ($this->issue)('add-oauth', 1);
    ($this->issue)('add-oauth', 1, $specTypeId);

    expect(DB::table('identifiers')->count())->toBe(2);
});

// A case- or accent-insensitive collation would merge distinct themes onto one number.
test('normalized names are compared exactly, not by collation folding', function () {
    ($this->issue)('cafe', 1);
    ($this->issue)('café', 2);
    ($this->issue)('елка', 3);
    ($this->issue)('ёлка', 4);
    ($this->issue)('strasse', 5);
    ($this->issue)('straße', 6);

    expect(DB::table('identifiers')->count())->toBe(6);
});

test('a project referenced by the registry cannot be deleted', function () {
    ($this->issue)('add-oauth', 1);

    DB::table('projects')->where('id', $this->projectId)->delete();
})->throws(QueryException::class);

test('a key type referenced by the registry cannot be deleted', function () {
    ($this->issue)('add-oauth', 1);

    DB::table('key_types')->where('id', $this->keyTypeId)->delete();
})->throws(QueryException::class);

test('the author of an issued number cannot be deleted', function () {
    ($this->issue)('add-oauth', 1);
    DB::table('projects')->where('id', $this->projectId)->update(['created_by' => DB::table('users')->insertGetId([
        'name' => 'Bob', 'email' => 'bob@cas.ai',
    ])]);

    DB::table('users')->where('id', $this->userId)->delete();
})->throws(QueryException::class);

test('the api journal outlives its user', function () {
    $userId = DB::table('users')->insertGetId(['name' => 'Eve', 'email' => 'eve@cas.ai']);
    DB::table('api_logs')->insert([
        'user_id' => $userId, 'token_name' => 'laptop', 'method' => 'POST', 'endpoint' => 'api/v1/sequence/next',
        'payload' => json_encode(['type' => 'ADR']), 'status_code' => 200, 'duration_ms' => 12,
    ]);

    DB::table('users')->where('id', $userId)->delete();

    expect(DB::table('api_logs')->value('user_id'))->toBeNull()
        ->and(DB::table('api_logs')->value('created_at'))->not->toBeNull();
});

test('the registry migration refuses to roll back over issued numbers', function () {
    ($this->issue)('add-oauth', 1);

    $migration = require database_path('migrations/2026_09_23_100003_create_identifiers_table.php');
    $migration->down();
})->throws(RuntimeException::class, 'identifiers');
