<?php

namespace App\Console\Commands;

use App\Models\FaceData;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class EncryptBiometricEmbeddings extends Command
{
    protected $signature = 'biometric:encrypt-embeddings {--dry-run : Report rows that would be encrypted}';

    protected $description = 'Encrypt existing plaintext face_data.embedding values at rest';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $encrypted = 0;
        $skipped = 0;

        FaceData::query()
            ->whereNotNull('embedding')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$encrypted, &$skipped) {
                foreach ($rows as $faceData) {
                    $value = $faceData->getRawOriginal('embedding');

                    if (! is_string($value) || $this->isEncrypted($value)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $encrypted++;

                        continue;
                    }

                    $faceData->embedding = $value;
                    $faceData->save();
                    $encrypted++;
                }
            });

        $this->info(sprintf(
            'Embedding encryption complete: %d encrypted, %d already encrypted or empty.',
            $encrypted,
            $skipped,
        ));

        return self::SUCCESS;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
