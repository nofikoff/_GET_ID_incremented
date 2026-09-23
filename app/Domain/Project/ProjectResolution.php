<?php

namespace App\Domain\Project;

/**
 * What an origin resolves to (FR-009). `hint` is set exactly when issuance would be refused.
 */
final readonly class ProjectResolution
{
    /**
     * @param  list<AvailableKeyType>  $types
     */
    public function __construct(
        public string $projectKey,
        public bool $registered,
        public bool $active,
        public ?string $name,
        public array $types,
        public ?string $hint,
    ) {}
}
