<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('movies:enrich')->hourly();
Schedule::command('activitylog:clean')->daily();

// Dispatch-outbox draining. `withoutOverlapping(2)` (minutes) prevents
// two ticks from running concurrently when one batch takes longer than
// a minute, while bounding a stale lock to two minutes if a worker dies
// without releasing it. `runInBackground()` keeps the scheduler
// responsive for the other jobs. The dispatcher itself is also
// concurrency-safe via `lockForUpdate()` + Postgres `SKIP LOCKED`, so
// manual `php artisan outbox:dispatch` runs alongside the scheduler
// won't double-process a row.
Schedule::command('outbox:dispatch')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('outbox:prune')->daily();
