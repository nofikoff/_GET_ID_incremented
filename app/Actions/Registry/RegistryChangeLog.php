<?php

namespace App\Actions\Registry;

use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * FR-019: who changed the registry and how, as an application log entry rather than a table
 * (specs/002-admin-web-console/research.md R5). Only an actual change is traced.
 */
final class RegistryChangeLog
{
    public function created(User $admin, Project|KeyType $entity): void
    {
        $changes = collect($entity->only($entity->getFillable()))
            ->reject(fn (mixed $value): bool => $value === null)
            ->map(fn (mixed $value): array => [null, $value]);

        $this->write($admin, 'create', $entity, $changes->all());
    }

    /**
     * Call right after a save that ran: getChanges() still holds an earlier save's fields after one that did not.
     */
    public function updated(User $admin, Project|KeyType $entity): void
    {
        // getPrevious() holds raw column values; a model around them casts them the way the entity reads.
        $before = $entity->newInstance()->setRawAttributes($entity->getPrevious());

        $changes = collect($entity->getChanges())
            ->except($entity->getUpdatedAtColumn())
            ->map(fn (mixed $raw, string $field): array => [$before->getAttribute($field), $entity->getAttribute($field)]);

        if ($changes->isNotEmpty()) {
            $this->write($admin, 'update', $entity, $changes->all());
        }
    }

    /**
     * Runs the EnabledKeyTypes::replace() call and traces the pairs it changed. A refused set throws out of
     * $replace before anything is traced. `changed` on the result is that same comparison, so a caller after
     * only a flash message (FR-009: "saved" vs "nothing changed") does not repeat it.
     *
     * @param  Closure(): Collection<int, ProjectKeyType>  $replace
     */
    public function keyTypesSet(User $admin, Project $project, Closure $replace): KeyTypesSetResult
    {
        // Read outside replace()'s row lock: a set racing in from another administrator can make it stale, which a trace tolerates.
        $before = $this->pairsOf($project);

        $pairs = $replace();

        // A pair that did not exist reads null on both fields: its row, counter included, was created by this set.
        $changes = collect($this->pairsOf($project))
            ->map(fn (array $after, string $code): array => collect($after)
                ->map(fn (bool|int $value, string $field): array => [$before[$code][$field] ?? null, $value])
                ->reject(fn (array $change): bool => $change[0] === $change[1])
                ->all())
            ->filter();

        if ($changes->isNotEmpty()) {
            $this->write($admin, 'set_key_types', $project, $changes->all());
        }

        return new KeyTypesSetResult($pairs, $changes->isNotEmpty());
    }

    /**
     * @return array<string, array{enabled: bool, seed_sequence: int}>
     */
    private function pairsOf(Project $project): array
    {
        return ProjectKeyType::query()->whereBelongsTo($project)->with('keyType')->get()
            ->mapWithKeys(fn (ProjectKeyType $pair): array => [
                $pair->keyType->code => ['enabled' => $pair->is_enabled, 'seed_sequence' => $pair->seed_sequence],
            ])
            ->all();
    }

    /**
     * @param  array<string, array<mixed>>  $changes  field => [before, after], or type code => field => [before, after]
     */
    private function write(User $admin, string $operation, Project|KeyType $entity, array $changes): void
    {
        // The change is already made: a trace that cannot be written is reported, never thrown at the caller (FR-019).
        rescue(fn () => Log::info('registry change', [
            'admin_id' => $admin->getKey(),
            'admin_email' => $admin->email,
            'operation' => $operation,
            'entity' => $entity instanceof Project ? 'project' : 'key_type',
            'entity_id' => $entity->getKey(),
            'changes' => $changes,
        ]));
    }
}
