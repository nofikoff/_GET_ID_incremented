<?php

namespace Tests\Concurrency;

use Illuminate\Process\Pool;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Assert;

/**
 * Runs each request in its own `php artisan getid:issue` process, all released at one shared instant
 * (research.md R3): without that mark, cold starts serialize the processes and a broken issuer passes.
 */
final class IssueRace
{
    // Fifty concurrent Laravel boots take about four seconds on the local stack; the mark has to clear all of them.
    private const START_DELAY_MS = 10_000;

    /**
     * @param  list<array{string, string, string}>  $requests  project key, key type code, theme
     * @return list<array{sequence_number: int, is_new: bool, waited_ms: int}>
     */
    public static function run(array $requests): array
    {
        $at = (int) floor(microtime(true) * 1000) + self::START_DELAY_MS;

        // Stated, not inherited: a child on another database would race nothing and could not fail.
        $environment = [
            'APP_ENV' => app()->environment(),
            'DB_CONNECTION' => DB::getDefaultConnection(),
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'DB_URL' => '',
        ];

        $results = Process::pool(function (Pool $pool) use ($requests, $at, $environment): void {
            foreach ($requests as $index => [$projectKey, $type, $name]) {
                $pool->as((string) $index)
                    ->path(base_path())
                    ->env($environment)
                    ->timeout(120)
                    ->command([PHP_BINARY, 'artisan', 'getid:issue', $projectKey, $type, $name, "--at={$at}"]);
            }
        })->start()->wait();

        return $results->collect()
            ->map(function (ProcessResult $result, int|string $index): array {
                Assert::assertTrue($result->successful(), "Process {$index} failed:\n{$result->errorOutput()}{$result->output()}");

                return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
            })
            ->each(fn (array $issued, int|string $index) => Assert::assertGreaterThan(
                0,
                $issued['waited_ms'],
                "Process {$index} booted after the start mark, so the requests did not overlap; raise START_DELAY_MS.",
            ))
            ->values()
            ->all();
    }
}
