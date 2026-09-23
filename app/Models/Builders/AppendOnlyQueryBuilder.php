<?php

namespace App\Models\Builders;

use App\Domain\Sequence\Exceptions\RegistryIsAppendOnly;
use Illuminate\Database\Query\Builder;

/**
 * Every Eloquent write — save, update, increment, touch, upsert, delete, relation delete — ends in one
 * of these three base-builder calls, so guarding them here closes all of them at once. withdraw() is the
 * one removal left open, for SequenceWithdrawer only (specs/003-delete-last-identifier/research.md R1).
 */
class AppendOnlyQueryBuilder extends Builder
{
    /**
     * Reach it through toBase(): the Eloquent builder forwards unknown methods and returns itself, not the count.
     */
    public function withdraw(): int
    {
        return parent::delete();
    }

    public function update(array $values): never
    {
        throw RegistryIsAppendOnly::attempted('update');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<array-key, mixed>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw RegistryIsAppendOnly::attempted('upsert');
    }

    public function delete($id = null): never
    {
        throw RegistryIsAppendOnly::attempted('delete');
    }
}
