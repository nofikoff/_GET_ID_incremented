<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\WaitsForStartMark;
use App\Domain\Project\ProjectKey;
use App\Domain\Sequence\Exceptions\NotTheLastIdentifier;
use App\Domain\Sequence\SequenceWithdrawer;
use App\Models\Identifier;
use App\Models\ProjectKeyType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Hidden;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The withdrawal's process entry point in the race suite, next to getid:issue
 * (specs/003-delete-last-identifier/research.md R7). A refusal is an outcome of the race, not a failure.
 */
#[Signature('getid:withdraw
    {project_key : Project key or repository origin}
    {type : Key type code}
    {number : Sequence number to take off}
    {--at= : Unix time in milliseconds to wait for before withdrawing}')]
#[Description('Withdraw a pair\'s last number and print the outcome as JSON')]
#[Hidden]
final class WithdrawIdentifierCommand extends Command
{
    use WaitsForStartMark;

    public function handle(SequenceWithdrawer $withdrawer): int
    {
        $waitedMs = $this->waitForStartMark();
        if ($waitedMs === null) {
            return self::INVALID;
        }

        $identifier = $this->inPair(Identifier::query())->where('sequence_number', (int) $this->argument('number'))->firstOrFail();

        try {
            $withdrawn = true;
            $lastSequence = $withdrawer->withdraw($identifier)->lastSequence;
        } catch (NotTheLastIdentifier) {
            $withdrawn = false;
            $lastSequence = $this->inPair(ProjectKeyType::query())->sole()->last_sequence;
        }

        $this->output->writeln(json_encode([
            'withdrawn' => $withdrawn,
            'last_sequence' => $lastSequence,
            'waited_ms' => $waitedMs,
        ], JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    /**
     * @template TModel of Identifier|ProjectKeyType
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inPair(Builder $query): Builder
    {
        $key = ProjectKey::fromOrigin((string) $this->argument('project_key'))->value;

        return $query
            ->whereHas('project', fn (Builder $project) => $project->where('key', $key))
            ->whereHas('keyType', fn (Builder $keyType) => $keyType->where('code', (string) $this->argument('type')));
    }
}
