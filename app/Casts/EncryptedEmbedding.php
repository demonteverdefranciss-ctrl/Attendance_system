<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts biometric embedding vectors at rest (RA 10173).
 * Accepts raw binary or string; transparently reads legacy plaintext rows.
 */
class EncryptedEmbedding implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        try {
            $decoded = base64_decode(Crypt::decryptString($value), true);

            return $decoded !== false ? $decoded : Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = is_string($value) ? $value : json_encode($value);

        return Crypt::encryptString(base64_encode($raw));
    }
}
