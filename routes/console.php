<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('facebook:dispatch-scheduled-posts')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('facebook:sync-insights --days=30')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('facebook:refresh-tokens')
    ->daily()
    ->withoutOverlapping();
