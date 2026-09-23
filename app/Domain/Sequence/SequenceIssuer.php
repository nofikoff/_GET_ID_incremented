<?php

namespace App\Domain\Sequence;

use App\Domain\KeyType\DocumentName;
use App\Domain\KeyType\IdentifierFormat;
use App\Domain\Project\ProjectKey;
use App\Domain\Sequence\Exceptions\DomainRejection;
use App\Domain\Sequence\Exceptions\InactiveKeyType;
use App\Domain\Sequence\Exceptions\InactiveProject;
use App\Domain\Sequence\Exceptions\SequenceNumberCollision;
use App\Domain\Sequence\Exceptions\TypeNotEnabled;
use App\Domain\Sequence\Exceptions\UnknownProject;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The one place a number is allocated; REST and MCP both call it (principle VI).
 *
 * Allocation locks the pair's counter row (research.md R1). Call it outside any open transaction: the
 * re-read after a lost same-theme race must see the winner's commit, which an older snapshot would hide.
 */
final class SequenceIssuer
{
    // Retries cover deadlocks only; a unique violation is never retried, it is resolved below.
    private const ATTEMPTS = 3;

    /**
     * @throws DomainRejection
     * @throws SequenceNumberCollision
     */
    public function issue(string $projectKey, string $type, string $name, ?User $author = null): IssuedIdentifier
    {
        $key = ProjectKey::fromOrigin($projectKey);
        // Resolved before the theme is checked, so a refusal for an unknown project always names its key (SC-003).
        $project = $this->project($key);
        $documentName = DocumentName::fromString($name);
        $keyType = $this->keyType($key, $type);

        // A repeat allocates nothing, so it is answered even for a retired pair (principle I).
        $issued = $this->find($project, $keyType, $documentName);
        if ($issued !== null) {
            return $this->present($project, $keyType, $issued, isNew: false);
        }

        $counter = $this->openCounter($key, $project, $keyType);
        $attempted = null;

        try {
            $issued = DB::transaction(function () use ($counter, $documentName, $author, &$attempted): Identifier {
                $locked = ProjectKeyType::query()->lockForUpdate()->findOrFail($counter->id);
                $attempted = $locked->nextSequence();

                $locked->last_sequence = $attempted;
                $locked->save();

                return $this->insert($locked, $documentName, $attempted, $author);
            }, self::ATTEMPTS);
        } catch (UniqueConstraintViolationException $violation) {
            // Caught out here, not inside the closure: only a propagated exception rolls the increment back,
            // otherwise the loser of a same-theme race would burn a number (FR-004a).
            $issued = $this->find($project, $keyType, $documentName)
                ?? throw SequenceNumberCollision::at($project->key, $keyType->code, (int) $attempted, $violation);

            return $this->present($project, $keyType, $issued, isNew: false);
        }

        return $this->present($project, $keyType, $issued, isNew: true);
    }

    /**
     * Retired projects, types and pairs stay readable; only a pair that never existed is refused.
     *
     * @throws DomainRejection
     */
    public function list(string $projectKey, string $type): IdentifierList
    {
        $key = ProjectKey::fromOrigin($projectKey);
        $project = $this->project($key);
        $keyType = $this->keyType($key, $type);

        if (! $this->counter($project, $keyType) instanceof ProjectKeyType) {
            throw TypeNotEnabled::inProject($key, $type);
        }

        $items = Identifier::query()
            ->whereBelongsTo($project)
            ->whereBelongsTo($keyType)
            ->orderByDesc('sequence_number')
            ->get()
            ->map(fn (Identifier $identifier): IssuedIdentifier => $this->present($project, $keyType, $identifier, isNew: false))
            ->all();

        return new IdentifierList($project->key, $keyType->code, array_values($items));
    }

    private function project(ProjectKey $key): Project
    {
        return Project::query()->where('key', $key->value)->first() ?? throw UnknownProject::forKey($key);
    }

    /**
     * An unknown code reads as "not enabled here": the list of types is for administrators only (FR-017).
     */
    private function keyType(ProjectKey $key, string $type): KeyType
    {
        return KeyType::query()->where('code', $type)->first() ?? throw TypeNotEnabled::inProject($key, $type);
    }

    private function counter(Project $project, KeyType $keyType): ?ProjectKeyType
    {
        return ProjectKeyType::query()
            ->where('project_id', $project->getKey())
            ->where('key_type_id', $keyType->getKey())
            ->first();
    }

    /**
     * FR-015, in the order that tells the client what to ask for: a retired type cannot be enabled anywhere,
     * so it is reported before "not enabled here".
     */
    private function openCounter(ProjectKey $key, Project $project, KeyType $keyType): ProjectKeyType
    {
        if (! $project->is_active) {
            throw InactiveProject::forKey($key);
        }
        if (! $keyType->is_active) {
            throw InactiveKeyType::inProject($key, $keyType->code);
        }

        $counter = $this->counter($project, $keyType);
        if ($counter === null || ! $counter->is_enabled) {
            throw TypeNotEnabled::inProject($key, $keyType->code);
        }

        return $counter;
    }

    private function find(Project $project, KeyType $keyType, DocumentName $name): ?Identifier
    {
        return Identifier::query()
            ->whereBelongsTo($project)
            ->whereBelongsTo($keyType)
            ->where('name_slug', $name->slug)
            ->first();
    }

    private function insert(ProjectKeyType $counter, DocumentName $name, int $number, ?User $author): Identifier
    {
        $identifier = new Identifier(['name' => $name->original, 'name_slug' => $name->slug, 'sequence_number' => $number]);
        $identifier->project_id = $counter->project_id;
        $identifier->key_type_id = $counter->key_type_id;
        $identifier->creator()->associate($author);
        $identifier->save();

        return $identifier;
    }

    private function present(Project $project, KeyType $keyType, Identifier $identifier, bool $isNew): IssuedIdentifier
    {
        return new IssuedIdentifier(
            projectKey: $project->key,
            type: $keyType->code,
            name: $identifier->name,
            sequenceNumber: $identifier->sequence_number,
            formattedId: IdentifierFormat::parse($keyType->format_template)
                ->format($identifier->sequence_number, DocumentName::fromString($identifier->name)),
            isNew: $isNew,
            issuedAt: $identifier->created_at->toImmutable(),
        );
    }
}
