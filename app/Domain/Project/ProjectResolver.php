<?php

namespace App\Domain\Project;

use App\Domain\Sequence\Exceptions\InactiveProject;
use App\Domain\Sequence\Exceptions\UnknownProject;
use App\Domain\Sequence\Exceptions\UnparsableOrigin;
use App\Models\Project;
use App\Models\ProjectKeyType;

/**
 * Answers about the one origin asked for and never about other projects (FR-009); REST and MCP share it.
 */
final class ProjectResolver
{
    /**
     * @throws UnparsableOrigin
     */
    public function resolve(string $origin): ProjectResolution
    {
        $key = ProjectKey::fromOrigin($origin);
        $project = Project::query()->where('key', $key->value)->first();

        // The hint is the refusal the client would get from issuance, worded identically.
        if ($project === null) {
            return new ProjectResolution($key->value, false, false, null, [], UnknownProject::forKey($key)->getMessage());
        }

        $types = ProjectKeyType::query()
            ->whereBelongsTo($project)
            ->issuable()
            ->orderByTypeCode()
            ->with('keyType')
            ->get()
            ->map(fn (ProjectKeyType $pair): AvailableKeyType => new AvailableKeyType($pair->keyType->code, $pair->keyType->name, $pair->nextSequence()))
            ->values()
            ->all();

        return new ProjectResolution(
            $project->key,
            true,
            $project->is_active,
            $project->name,
            $types,
            $project->is_active ? null : InactiveProject::forKey($key)->getMessage(),
        );
    }
}
