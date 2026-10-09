<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\DateBoundaryParser;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;

/**
 * Shared rules and typed accessors for date-range filters.
 *
 * Every index publishes the bare pair `filter[from]`/`filter[to]` on its `created_at` column, so
 * one filter shape works on every resource and a client learns it once. Both bounds resolve
 * through `DateBoundaryParser`: a bare calendar date expands to the whole UTC day, a date-time
 * resolves to the instant it names.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait AppliesDateRangeFilters
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the resolved inclusive lower bound for a `…_from` key.
     *
     * A bare calendar date (`Y-m-d`, zero-padded or not) resolves to the start
     * of that UTC day; an offset-bearing date-time resolves to the instant it
     * names, normalised to UTC. See `DateBoundaryParser::resolve()` for the
     * semantics.
     *
     * @param  string               $fromKey the fully qualified `filter[…]` key, for example `filter.from`
     * @return CarbonImmutable|null the inclusive lower bound, or null when omitted
     */
    public function fromBoundary(string $fromKey): ?CarbonImmutable
    {
        return $this->boundary($fromKey, endOfDay: false);
    }

    /**
     * Get the resolved inclusive upper bound for a `…_to` key.
     *
     * A bare calendar date (`Y-m-d`, zero-padded or not) resolves to the end
     * of that UTC day, so the bound includes the whole day it names rather
     * than cutting off at midnight. An offset-bearing date-time resolves to
     * the instant it names, normalised to UTC. See
     * `DateBoundaryParser::resolve()` for the semantics.
     *
     * @param  string               $toKey the fully qualified `filter[…]` key, for example `filter.to`
     * @return CarbonImmutable|null the inclusive upper bound, or null when omitted
     */
    public function toBoundary(string $toKey): ?CarbonImmutable
    {
        return $this->boundary($toKey, endOfDay: true);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Build the validation rules for one date-range pair.
     *
     * The upper-bound rule carries the cross-field check: it resolves both
     * bounds and rejects the pair when the resolved `to` precedes the resolved
     * `from`. Comparing the raw strings instead would reject a valid mixed pair
     * such as `from=2026-10-05T15:00:00` with `to=2026-10-05`, because a bare
     * `Y-m-d` only expands to the end of its day during resolution. An
     * unparseable value falls through to the `date` rule, which reports it on
     * its own key.
     *
     * @param  string                           $fromKey the fully qualified lower-bound key
     * @param  string                           $toKey   the fully qualified upper-bound key
     * @return array<string, array<int, mixed>> the rules for both keys of the pair
     */
    protected function dateRangeFilterRules(string $fromKey, string $toKey): array
    {
        return [
            $fromKey => ['sometimes', 'date'],
            $toKey => [
                'sometimes',
                'date',
                function (string $attribute, mixed $value, Closure $fail) use ($fromKey, $toKey): void {
                    $from = $this->input($fromKey);

                    if (! is_string($from) || $from === '' || ! is_string($value)) {
                        return;
                    }

                    try {
                        $fromBoundary = DateBoundaryParser::resolve($from, endOfDay: false);
                        $toBoundary = DateBoundaryParser::resolve($value, endOfDay: true);
                    } catch (InvalidFormatException) {
                        return;
                    }

                    if ($toBoundary->isBefore($fromBoundary)) {
                        $fail(sprintf(
                            'The %s Value Must Be A Date After Or Equal To %s',
                            $toKey,
                            $fromKey,
                        ));
                    }
                },
            ],
        ];
    }

    /**
     * Message copy for one pair's `.date` rejections.
     *
     * Keyed `{attribute}.{rule}` for the same reason as every other dotted key
     * here: a bare `filter.to` entry would shadow the copy the
     * resolved-boundary closure raises for the same attribute.
     *
     * @param  string                $fromKey the fully qualified lower-bound key
     * @param  string                $toKey   the fully qualified upper-bound key
     * @return array<string, string> the message for each key's `date` rule
     */
    protected function dateRangeFilterMessages(string $fromKey, string $toKey): array
    {
        return [
            $fromKey.'.date' => sprintf('The %s Value Must Be A Date', $fromKey),
            $toKey.'.date' => sprintf('The %s Value Must Be A Date', $toKey),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a pair bound to its UTC instant, or null when the key is absent.
     *
     * A present-but-blank value never reaches this resolver: the `date` rule
     * rejects it first, so unlike a comma list (where blank means an empty
     * list), a blank date is malformed input and answers `422`.
     *
     * @param  string               $key      the fully qualified `filter[…]` key
     * @param  bool                 $endOfDay whether a bare date expands to the end of its day
     * @return CarbonImmutable|null the resolved boundary, or null when omitted
     */
    private function boundary(string $key, bool $endOfDay): ?CarbonImmutable
    {
        if (! $this->safe()->has($key)) {
            return null;
        }

        return DateBoundaryParser::resolve(
            $this->safe()->string($key)->toString(),
            endOfDay: $endOfDay,
        );
    }
}
