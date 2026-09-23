<?php

namespace App\Actions\Registry;

use App\Models\ProjectKeyType;
use Illuminate\Database\Eloquent\Collection;

/**
 * keyTypesSet()'s outcome: the pairs now issuing, and whether the before/after comparison it already ran found
 * a change — so a caller that only needs to know whether to flash "saved" or "nothing changed" does not run
 * that comparison a second time.
 */
final readonly class KeyTypesSetResult
{
    /**
     * @param  Collection<int, ProjectKeyType>  $pairs
     */
    public function __construct(
        public Collection $pairs,
        public bool $changed,
    ) {}
}
