<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Frees unique columns while a row is archived so new records can reuse them.
 */
class SoftDeleteUnique
{
    public static function archive(Model $model, array $attributes): void
    {
        $updates = [];

        foreach ($attributes as $attribute) {
            $value = $model->getAttribute($attribute);
            if ($value === null || $value === '') {
                continue;
            }

            $value = (string) $value;
            if (str_contains($value, '__archived_')) {
                continue;
            }

            $suffix = '__archived_'.$model->getKey();
            $max = 190;
            $trimmed = Str::limit($value, max(1, $max - strlen($suffix)), '');
            $updates[$attribute] = $trimmed.$suffix;
        }

        if ($updates !== []) {
            $model->forceFill($updates)->saveQuietly();
        }
    }

    public static function restore(Model $model, array $attributes): void
    {
        $updates = [];
        $id = (string) $model->getKey();

        foreach ($attributes as $attribute) {
            $value = $model->getAttribute($attribute);
            if ($value === null || $value === '') {
                continue;
            }

            $value = (string) $value;
            $marker = '__archived_'.$id;
            if (str_ends_with($value, $marker)) {
                $updates[$attribute] = substr($value, 0, -strlen($marker));
            }
        }

        if ($updates !== []) {
            $model->forceFill($updates)->saveQuietly();
        }
    }
}
