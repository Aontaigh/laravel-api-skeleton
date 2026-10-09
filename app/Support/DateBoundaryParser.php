<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Resolves a validated date-range filter value to its UTC boundary.
 *
 * A range filter accepts two vocabularies, and only the shape of the value
 * tells them apart: a bare calendar date names a whole day, while a date-time
 * names an instant. A colon probe cannot separate them, because RFC 3339
 * section 4.3 permits a colon-free internet date-time (`2026-10-05T15Z`,
 * `2026-10-05 +0200`) that Laravel's `date` rule accepts and Carbon resolves
 * to a precise instant; treating those as calendar dates silently binds the
 * wrong boundary.
 */
final class DateBoundaryParser
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * The bare calendar-date shape `Y-m-d`, zero-padded or not.
     *
     * Laravel's `date` rule accepts `2026-10-5` exactly as it accepts
     * `2026-10-05`, so the resolver has to agree with that vocabulary rather
     * than with the narrower `^\d{4}-\d{2}-\d{2}$`: a `2026-10-5` input that
     * validates but binds midnight silently excludes the whole day the caller
     * named. The pattern anchors with `\z`, not `$`, which PCRE also matches
     * before a final newline.
     */
    private const BARE_DATE_PATTERN = '/^\d{4}-\d{1,2}-\d{1,2}\z/';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a date input to its UTC range boundary.
     *
     * A bare calendar date expands to the whole UTC day - `startOfDay` for a
     * `…_from` bound, `endOfDay` for a `…_to` bound - so a range that names
     * only the day still sees rows written at any time that day. Any other
     * value is a date-time and resolves to the single instant it names,
     * whether or not it carries a colon and whether or not it carries an
     * offset.
     *
     * The boundary is normalised to UTC before it leaves the parser because
     * the timestamp columns store UTC and Laravel formats a date binding in
     * the object's own timezone: an offset-bearing
     * `2026-10-05T15:00:00+02:00` left as parsed would compare as `15:00:00Z`
     * against the UTC column and silently hide the two hours the caller asked
     * for.
     *
     * @param  string          $value    the validated date input
     * @param  bool            $endOfDay whether a bare date expands to the end of the day (upper bound)
     * @return CarbonImmutable the boundary instant, in UTC
     */
    public static function resolve(string $value, bool $endOfDay): CarbonImmutable
    {
        $date = CarbonImmutable::parse($value, config()->string('app.timezone', 'UTC'))->utc();

        if (! self::isBareCalendarDate($value)) {
            return $date;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * Whether the value is a bare calendar date rather than a date-time.
     *
     * Matched by shape, not by the absence of a colon: `2026-10-05T15Z` and
     * `2026-10-05 +0200` are colon-free date-times that name an instant.
     * Surrounding whitespace is ignored so a padded ` 2026-10-05` still counts
     * as the day it names.
     *
     * @param  string $value the validated date input
     * @return bool   true when the value is a `Y-m-d` calendar date
     */
    public static function isBareCalendarDate(string $value): bool
    {
        return preg_match(self::BARE_DATE_PATTERN, trim($value)) === 1;
    }
}
