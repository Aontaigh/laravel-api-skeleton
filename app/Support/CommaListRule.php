<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates each value in a comma-separated filter against an inner rule.
 *
 * Applying `Rule::in` to the whole raw value would reject a list outright, because the string
 * `active,suspended` is not itself a member of the allow-list. Each part has to be validated
 * separately so the caller learns *which* value was wrong.
 *
 * The inner rule is applied to each part on its own rather than to the joined string, so an
 * `integer` rule rejects `1,x` with a message naming `x`. Validating the joined string would
 * make the type of every part unverifiable.
 *
 * The rule also enforces the list cap, which is why it exists rather than a bare `Rule::in`:
 * without it the cap would have to be a second enforcement path, and one path means one place
 * to get wrong.
 *
 * Every invalid part is reported, not just the first, so a caller sending `1,abc,def` can
 * correct the whole list from one `422` rather than discovering the bad values one round trip
 * at a time.
 */
final class CommaListRule implements ValidationRule
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The flat key each part is validated under, so a dotted attribute name cannot nest. */
    private const PART_KEY = 'value';

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CommaListRule.
     *
     * @param int   $max   the largest list permitted
     * @param mixed $inner the rule each part must satisfy, in any shape `validator()` accepts
     */
    public function __construct(
        private readonly int $max,
        private readonly mixed $inner = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether every value in the list is allowed and the list is within its cap.
     *
     * @param  string  $attribute the validated attribute name
     * @param  mixed   $value     the submitted value
     * @param  Closure $fail      the callback that records a validation error
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            $parts = CommaListParser::split($value, $this->max);
        } catch (ListTooLongException $exception) {
            $fail($exception->getMessage());

            return;
        }

        if ($this->inner === null) {
            return;
        }

        /*
         * The part is validated under a flat key rather than the real attribute name, because
         * Laravel resolves a dotted key as a nested path. Validating against `filter.status`
         * would read `filter` as an array, find nothing, and silently pass every value, so the
         * allow-list would never reject anything.
         */
        foreach ($parts as $part) {
            $validator = validator([self::PART_KEY => $part], [self::PART_KEY => $this->inner]);

            if ($validator->fails()) {
                $segments = explode('.', $attribute);

                $fail(sprintf(
                    'The Selected %s Value Is Invalid: %s',
                    end($segments),
                    $part,
                ));
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    /**
     * Build a rule allowing only the listed values.
     *
     * @param  int                $max     the largest list permitted
     * @param  array<int, string> $allowed the permitted values
     * @return self
     */
    public static function in(int $max, array $allowed): self
    {
        return new self($max, Rule::in($allowed));
    }
}
