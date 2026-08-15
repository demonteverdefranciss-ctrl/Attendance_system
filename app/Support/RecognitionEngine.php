<?php

namespace App\Support;

use App\Models\AppSetting;

class RecognitionEngine
{
    public const KEY = 'recognition_engine';

    public static function current(): string
    {
        try {
            $stored = AppSetting::query()->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            $stored = null;
        }

        return self::normalize($stored ?? config('recognition.engine', 'lbph'));
    }

    public static function set(string $engine): string
    {
        $engine = self::normalize($engine);

        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $engine],
        );

        return $engine;
    }

    public static function normalize(?string $engine): string
    {
        return $engine === 'arcface' ? 'arcface' : 'lbph';
    }
}
