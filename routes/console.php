<?php

use App\Models\NotificationLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trigger deadline-based draws for rounds that didn't sell out in time, and
// recover any round left stuck in "Drawing" by a lost queue job.
// withoutOverlapping(10): the mutex auto-expires after 10 minutes so a killed
// run (shared hosts do this) can't wedge the schedule for the default 24h.
Schedule::command('lottery:check-deadlines')->everyMinute()->withoutOverlapping(10);

// Shared-hosting queue worker: no daemon needed. The same single cron that
// runs `schedule:run` drains the queue once a minute and exits.
//  --stop-when-empty  exit as soon as the queue is drained
//  --max-time=50      always exit before the next minute's tick
//  --tries=3          retry transient failures (per-job $tries still wins)
// Runs AFTER check-deadlines so a deadline draw dispatched above is picked up
// in the same tick.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(10);

// Keep the Telegram delivery log from growing unbounded.
Schedule::command('model:prune', ['--model' => [NotificationLog::class]])->daily();
