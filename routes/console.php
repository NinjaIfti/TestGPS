<?php

use App\Jobs\SyncLocationsToDatabase;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule location sync from Redis to MySQL
// Runs every minute by default (configurable via GPS_SYNC_INTERVAL env)
Schedule::job(new SyncLocationsToDatabase())
    ->everyMinute()
    ->name('sync-locations')
    ->withoutOverlapping()
    ->onOneServer();
