<?php

namespace App\Queries;

use App\Models\Identifier;
use App\Models\ProjectKeyType;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * FR-013: what one pair issued, for the project card. Several pairs share the page, so each pages on its own
 * parameter and its links carry the others' (specs/002-admin-web-console/research.md R6).
 */
final class IssuedIdentifiers
{
    /**
     * @return LengthAwarePaginator<int, Identifier>
     */
    public function forPair(ProjectKeyType $pair, int $perPage = 50): LengthAwarePaginator
    {
        return Identifier::query()
            ->where('project_id', $pair->project_id)
            ->where('key_type_id', $pair->key_type_id)
            // The (project_id, key_type_id, sequence_number) unique index read backwards: no filesort.
            ->orderByDesc('sequence_number')
            ->with('creator')
            ->paginate($perPage, pageName: 'page_'.$pair->keyType->code)
            ->withQueryString();
    }
}
