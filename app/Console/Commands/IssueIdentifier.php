<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\WaitsForStartMark;
use App\Domain\Sequence\Exceptions\DomainRejection;
use App\Domain\Sequence\SequenceIssuer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Hidden;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The process entry point of the race suite (tests/Concurrency). `--at` is the shared start mark
 * (research.md R3); `waited_ms` goes negative when the process booted too late to overlap the others.
 */
#[Signature('getid:issue
    {project_key : Project key or repository origin}
    {type : Key type code}
    {name : Document theme}
    {--at= : Unix time in milliseconds to wait for before issuing}')]
#[Description('Issue a number and print it as JSON')]
#[Hidden]
final class IssueIdentifier extends Command
{
    use WaitsForStartMark;

    public function handle(SequenceIssuer $issuer): int
    {
        $waitedMs = $this->waitForStartMark();
        if ($waitedMs === null) {
            return self::INVALID;
        }

        try {
            $issued = $issuer->issue(
                (string) $this->argument('project_key'),
                (string) $this->argument('type'),
                (string) $this->argument('name'),
            );
        } catch (DomainRejection $rejection) {
            $this->error("{$rejection->errorCode()}: {$rejection->getMessage()}");

            return self::FAILURE;
        }

        $this->output->writeln(json_encode([
            'sequence_number' => $issued->sequenceNumber,
            'is_new' => $issued->isNew,
            'waited_ms' => $waitedMs,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
