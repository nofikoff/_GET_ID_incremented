<?php

use App\Models\ApiLog;
use App\Models\Identifier;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

// The journal is kept for a horizon (config/getid.php); the registry is never pruned.

beforeEach(function () {
    $this->entryAt = fn (Carbon $at): ApiLog => tap(
        new ApiLog(['method' => 'GET', 'endpoint' => '/api/v1/sequence/list', 'status_code' => 200, 'duration_ms' => 5]),
        fn (ApiLog $entry) => $entry->forceFill(['created_at' => $at])->save(),
    );
    $this->prune = fn () => $this->artisan('model:prune', ['--model' => [ApiLog::class]])->assertSuccessful();
});

test('entries older than the horizon go, newer ones and the registry stay', function () {
    config(['getid.api_log_retention_days' => 90]);
    $old = ($this->entryAt)(now()->subDays(91));
    $recent = ($this->entryAt)(now()->subDays(89));
    $issued = Identifier::factory()->create(['created_at' => now()->subYears(2)]);

    ($this->prune)();

    expect(ApiLog::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(ApiLog::query()->find($old->id))->toBeNull()
        ->and(Identifier::query()->find($issued->id))->not->toBeNull();
});

test('the horizon comes from configuration', function () {
    config(['getid.api_log_retention_days' => 30]);
    ($this->entryAt)(now()->subDays(31));
    $kept = ($this->entryAt)(now()->subDays(29));

    ($this->prune)();

    expect(ApiLog::query()->pluck('id')->all())->toBe([$kept->id]);
});

test('the scheduler prunes the journal daily', function () {
    // routes/console.php, where the schedule lives, loads when the console kernel bootstraps.
    $this->app->make(Kernel::class)->bootstrap();

    $pruning = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'model:prune'));

    expect($pruning)->toHaveCount(1)
        ->and($pruning->first()->command)->toContain(ApiLog::class)
        ->and($pruning->first()->expression)->toBe('0 0 * * *');
});
