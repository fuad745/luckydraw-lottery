<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trigger deadline-based draws for rounds that didn't sell out in time.
Schedule::command('lottery:check-deadlines')->everyMinute()->withoutOverlapping();
