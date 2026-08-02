<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('kpi:ai:queue-pending --recover-stale --limit=100 --no-interaction')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);
