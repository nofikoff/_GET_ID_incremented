<?php

namespace App\Domain\Project;

use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The set of key types a project serves. The link row carries the counter, so a type dropped from the set
 * is disabled and never deleted (FR-016), and the seed is checked while holding the same row lock
 * SequenceIssuer takes, so no issuance can slip between the check and the write (FR-014b).
 */
final class EnabledKeyTypes
{
    // Retries cover deadlocks only.
    private const ATTEMPTS = 3;

    /**
     * @param  list<array{code: string, seed_sequence: int|null}>  $types  a null seed keeps the pair's current one
     * @return Collection<int, ProjectKeyType> the pairs now issuing, by type code
     *
     * @throws SeedBelowIssued
     * @throws RetiredKeyType
     */
    public function replace(Project $project, array $types): Collection
    {
        return DB::transaction(function () use ($project, $types): Collection {
            $counters = ProjectKeyType::query()->whereBelongsTo($project)->lockForUpdate()->get()->keyBy('key_type_id');
            $enabled = [];

            // Every check runs before the first write, so a refused set changes nothing.
            foreach ($types as $position => ['code' => $code, 'seed_sequence' => $seed]) {
                // Shared lock: a retirement committing after validation is either seen here or waits for this set.
                $keyType = KeyType::query()->where('code', $code)->sharedLock()->firstOrFail();
                if (! $keyType->is_active) {
                    throw RetiredKeyType::at($position, $keyType->code);
                }

                $counter = $counters->get($keyType->id) ?? new ProjectKeyType([
                    'project_id' => $project->id,
                    'key_type_id' => $keyType->id,
                    'seed_sequence' => 0,
                    'last_sequence' => 0,
                ]);

                if ($seed !== null && $seed < $counter->last_sequence) {
                    throw SeedBelowIssued::at($position, $keyType->code, $seed, $counter->last_sequence);
                }

                $counter->seed_sequence = $seed ?? $counter->seed_sequence;
                $counter->is_enabled = true;
                $enabled[$keyType->id] = $counter;
            }

            foreach ($counters->reject(fn (ProjectKeyType $counter): bool => isset($enabled[$counter->key_type_id])) as $dropped) {
                $dropped->is_enabled = false;
                $dropped->save();
            }
            foreach ($enabled as $counter) {
                $counter->save();
            }

            return ProjectKeyType::query()->whereBelongsTo($project)->issuable()->orderByTypeCode()->with('keyType')->get();
        }, self::ATTEMPTS);
    }
}
