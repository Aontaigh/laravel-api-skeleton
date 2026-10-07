<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Throwable;

/**
 * Raised when a comma-separated filter carries more values than the filter permits.
 *
 * A dedicated exception rather than a validation error so the FormRequest can
 * catch it and report it against the offending query parameter, which is what
 * the caller needs in order to fix the request. Truncating the list instead
 * would return a partial answer that looks complete.
 */
final class ListTooLongException extends RuntimeException
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ListTooLongException.
     *
     * @param int            $given    the number of values supplied
     * @param int            $max      the largest list permitted
     * @param int            $code     the exception code
     * @param Throwable|null $previous the previous exception, when wrapping one
     */
    public function __construct(
        public readonly int $given,
        public readonly int $max,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Filter Accepts At Most %d Value(s), %d Given', $max, $given),
            $code,
            $previous,
        );
    }
}
