<?php

use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Mcp');

// A transaction per test would make row locks meaningless, so the race suite commits and truncates (research.md R3).
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/**
 * A registered project with one key type enabled in it: gitlab.cas.ai/team/backend and ADR unless overridden.
 *
 * @param  Project|array<string, mixed>  $project
 * @param  KeyType|array<string, mixed>  $keyType
 * @param  array<string, mixed>  $counter
 */
function enabledPair(Project|array $project = [], KeyType|array $keyType = [], array $counter = []): ProjectKeyType
{
    return ProjectKeyType::factory()
        ->for($project instanceof Project ? $project : Project::factory()->state(['repo_url' => 'git@gitlab.cas.ai:team/backend.git', ...$project]))
        ->for($keyType instanceof KeyType ? $keyType : KeyType::factory()->state(['code' => 'ADR', ...$keyType]))
        ->create($counter);
}
