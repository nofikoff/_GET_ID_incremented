<?php

namespace App\Domain\Sequence;

/**
 * The numbers issued in one project and key type pair, newest first (FR-006).
 */
final readonly class IdentifierList
{
    /**
     * @param  list<IssuedIdentifier>  $items
     */
    public function __construct(
        public string $projectKey,
        public string $type,
        public array $items,
    ) {}
}
