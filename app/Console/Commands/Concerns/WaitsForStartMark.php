<?php

namespace App\Console\Commands\Concerns;

/**
 * The shared start mark of the race suite (tests/Concurrency, specs/001-incremental-id-registry/research.md R3).
 */
trait WaitsForStartMark
{
    /**
     * @return int|null milliseconds waited, negative when the process booted after the mark; null for a bad --at
     */
    private function waitForStartMark(): ?int
    {
        $at = $this->option('at');
        if ($at === null) {
            return 0;
        }
        if (filter_var($at, FILTER_VALIDATE_INT) === false) {
            $this->error('--at takes a Unix time in milliseconds.');

            return null;
        }

        $delayUs = (int) $at * 1000 - (int) (microtime(true) * 1_000_000);
        if ($delayUs > 0) {
            usleep($delayUs);
        }

        return intdiv($delayUs, 1000);
    }
}
