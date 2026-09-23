<?php

namespace App\Domain\Sequence;

use App\Domain\Sequence\Exceptions\NotTheLastIdentifier;
use App\Models\Builders\AppendOnlyQueryBuilder;
use App\Models\Identifier;
use App\Models\ProjectKeyType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Takes a pair's last number off and rolls its counter back to the new tail (spec 003, FR-001..FR-005).
 *
 * The pair's counter row lock is the transaction's first statement, and every read after it is a plain
 * consistent read: its read view is taken after the lock, so it sees every issuance committed before
 * (specs/003-delete-last-identifier/research.md R2). A read placed before the lock breaks that.
 */
final class SequenceWithdrawer
{
    // Retries cover deadlocks only, as for issuance.
    private const ATTEMPTS = 3;

    /**
     * @throws ModelNotFoundException the number is already gone, withdrawn by someone else
     * @throws NotTheLastIdentifier
     */
    public function withdraw(Identifier $identifier): WithdrawnIdentifier
    {
        return DB::transaction(function () use ($identifier): WithdrawnIdentifier {
            $pair = ProjectKeyType::query()
                ->where('project_id', $identifier->project_id)
                ->where('key_type_id', $identifier->key_type_id)
                ->lockForUpdate()
                ->firstOrFail();

            $current = Identifier::query()->findOrFail($identifier->getKey());

            $tail = $this->tailOf($pair);
            if ($tail !== null && $tail->sequence_number > $current->sequence_number) {
                throw NotTheLastIdentifier::tail($tail->formatted_id);
            }

            $removal = Identifier::query()->whereKey($current->getKey())->toBase();
            assert($removal instanceof AppendOnlyQueryBuilder);
            $removal->withdraw();

            $previousLastSequence = $pair->last_sequence;
            $pair->last_sequence = $this->tailOf($pair)->sequence_number ?? 0;
            $pair->save();

            return new WithdrawnIdentifier(
                formattedId: $current->formatted_id,
                name: $current->name,
                sequenceNumber: $current->sequence_number,
                previousLastSequence: $previousLastSequence,
                lastSequence: $pair->last_sequence,
                nextSequence: $pair->nextSequence(),
            );
        }, self::ATTEMPTS);
    }

    private function tailOf(ProjectKeyType $pair): ?Identifier
    {
        return Identifier::query()
            ->where('project_id', $pair->project_id)
            ->where('key_type_id', $pair->key_type_id)
            ->orderByDesc('sequence_number')
            ->first();
    }
}
