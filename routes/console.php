<?php

use App\Models\ApiLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Named explicitly, so a model made prunable later is not swept by this entry unseen. The host runs schedule:run every minute (quickstart.md).
Schedule::command('model:prune', ['--model' => [ApiLog::class]])->daily();
