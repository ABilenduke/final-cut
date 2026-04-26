<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('movies:enrich')->hourly();
Schedule::command('activitylog:clean')->daily();

// Dispatch-outbox draining. `withoutOverlapping(90)` prevents two ticks
// from running concurrently when one batch takes longer than a minute;
// `runInBackground()` keeps the scheduler responsive for the other jobs.
Schedule::command('outbox:dispatch')
    ->everyMinute()
    ->withoutOverlapping(90)
    ->runInBackground();

Schedule::command('outbox:prune')->daily();
