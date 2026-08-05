<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Support\ValidatedInput;

/**
 * Declares the request-input surface the Query-Param concerns depend on.
 *
 * The parse concerns are only ever composed into a FormRequest, and they need
 * exactly two things from it. Declaring those as real abstract methods turns
 * the dependency into a contract PHP enforces at compile time, instead of a
 * `@mixin` docblock hint: a host class that cannot satisfy them is a fatal
 * error rather than a runtime surprise. It also keeps the concerns readable
 * in an editor whose `@mixin` support is a paid feature.
 */
trait ReadsRequestInput
{
    /*
    |--------------------------------------------------------------------------
    | Abstract
    |--------------------------------------------------------------------------
    */

    /**
     * Get a container for the validated input.
     *
     * Signatures here must match Laravel's exactly, or PHP rejects the host
     * class. The return is narrowed to `ValidatedInput` from Laravel's
     * `ValidatedInput|array`, because the array is only returned when `$keys`
     * is passed — which these concerns never do, and the union would make
     * every chained `->string()` call a static analysis error.
     *
     * @param  array<int, string>|null $keys limit the container to these keys
     * @return ValidatedInput          the validated input container
     */
    abstract public function safe(?array $keys = null);

    /**
     * Retrieve a raw, unvalidated input item from the request.
     *
     * Needed because `after()` validation hooks have to inspect input that
     * has already failed its rules, which `safe()` has by then discarded.
     *
     * @param  string|null $key     the input key, or null for the whole bag
     * @param  mixed       $default returned when the key is absent
     * @return mixed       the raw input value
     */
    abstract public function input($key = null, $default = null);
}
