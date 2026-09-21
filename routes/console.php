<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// The database state is the durable outbox for paid Nomod attempts. This also
// turns an interrupted supplier request into a reviewable state rather than
// risking a duplicate StartPay call after a worker/server crash.
Schedule::command('payments:nomod:recover-at-fulfilments')
    ->everyMinute()
    ->withoutOverlapping();
