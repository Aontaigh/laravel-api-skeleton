<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalises user-facing plain-text fields before persistence.
 */
final class PlainText
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Strip HTML tags and control characters from a plain-text value.
     *
     * @param  string $value the raw input
     * @return string the sanitised plain text
     */
    public static function sanitize(string $value): string
    {
        $withoutTags = strip_tags($value);

        $withoutControls = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $withoutTags) ?? $withoutTags;

        return trim(preg_replace('/\s+/u', ' ', $withoutControls) ?? $withoutControls);
    }
}
