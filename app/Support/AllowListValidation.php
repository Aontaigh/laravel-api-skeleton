<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helpers for allow-list validation errors that tell callers what went wrong
 * and which values are supported.
 */
final class AllowListValidation
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Build a Title Case validation message listing rejected and supported values.
     *
     * @param  string             $label    the error prefix (e.g. `Unsupported Include`)
     * @param  array<int, string> $rejected the value(s) that failed the allow-list
     * @param  list<string>       $allowed  the whitelisted values for that key
     * @return string             the formatted validation message
     */
    public static function unsupportedMessage(
        string $label,
        array $rejected,
        array $allowed,
    ): string {
        return sprintf(
            '%s: %s (Supported: %s)',
            $label,
            implode(', ', array_values($rejected)),
            implode(', ', self::sorted($allowed)),
        );
    }

    /**
     * Return a sorted, reindexed copy of an allow-list.
     *
     * @param  list<string> $allowed the raw allow-list
     * @return list<string> the normalised allow-list
     */
    public static function sorted(array $allowed): array
    {
        $normalised = $allowed;
        sort($normalised);

        return $normalised;
    }
}
