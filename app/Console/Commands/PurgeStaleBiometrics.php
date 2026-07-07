<?php

namespace App\Console\Commands;

use App\Services\BiometricPrivacyService;
use Illuminate\Console\Command;

class PurgeStaleBiometrics extends Command
{
    protected $signature = 'biometric:purge-stale {--dry-run : Report what would be deleted without making changes}';

    protected $description = 'Remove biometric face data for students without consent or past the retention period (RA 10173)';

    public function handle(BiometricPrivacyService $privacy): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no records or files will be deleted.');
        }

        $result = $privacy->purgeStale($dryRun);

        $this->info(sprintf(
            'Biometric purge complete: %d record(s), %d file(s) (%d consent/inactive, %d stale past retention).',
            $result['records'],
            $result['files'],
            $result['consent_revoked'],
            $result['stale'],
        ));

        return self::SUCCESS;
    }
}
