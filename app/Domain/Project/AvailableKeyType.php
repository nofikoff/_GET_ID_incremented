<?php

namespace App\Domain\Project;

final readonly class AvailableKeyType
{
    public function __construct(
        public string $code,
        public string $name,
        public int $nextNumber,
    ) {}
}
