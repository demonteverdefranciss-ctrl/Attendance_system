<?php

namespace App\Console\Commands;

use App\Services\GoogleHolidaySync;
use Illuminate\Console\Command;
use Throwable;

class SyncGoogleHolidays extends Command
{
    protected $signature = 'attendance:sync-holidays';

    protected $description = 'Sync Google Calendar holidays into no-class days (skips session auto-open)';

    public function handle(GoogleHolidaySync $sync): int
    {
        if (! config('school_calendar.google_sync_enabled', true)) {
            $this->info('Google Calendar holiday sync is disabled.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->sync();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Google holidays synced: %d total, %d new, %d updated, %d removed, %d manual kept.',
            $result['total'],
            $result['imported'],
            $result['updated'],
            $result['removed'],
            $result['skipped_manual'],
        ));

        return self::SUCCESS;
    }
}
