<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-open / auto-close attendance windows. Requires the scheduler to run:
//   * * * * *  php artisan schedule:run   (cron on the server)
// or `php artisan schedule:work` locally during development.
Schedule::command('attendance:manage-sessions')->everyMinute();

// Purge biometric data without consent or past retention (RA 10173). Weekly on Sunday 02:00.
Schedule::command('biometric:purge-stale')->weeklyOn(0, '02:00');
