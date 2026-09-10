<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\EmailAddress;
use Illuminate\Support\Stringable;

/**
 * Lowercases the email attribute before validation on FormRequests that accept
 * user email addresses.
 *
 * Composed via {@see PreparesPlainTextAndEmail} and {@see PreparesAuthCredentials}.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait NormalisesAuthEmail
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Abstract
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the request input contains a given key.
     *
     * Signatures here must match Laravel's exactly, or PHP rejects the host
     * class.
     *
     * @param  string|array<int, string> $key the input key, or a list of keys
     * @return bool                      whether any given key is present
     */
    abstract public function has($key);

    /**
     * Retrieve an input value wrapped in a Stringable accessor.
     *
     * @param  string     $key     the input key
     * @param  mixed      $default returned when the key is absent
     * @return Stringable the input value accessor
     */
    abstract public function string($key, $default = null);

    /**
     * Merge new input into the request's current input array.
     *
     * @param  array<string, mixed> $input the values to merge into the input
     * @return static               the request with the merged input
     */
    abstract public function merge(array $input);

    /*
    |--------------------------------------------------------------------------
    | Preparation
    |--------------------------------------------------------------------------
    */

    /**
     * Store the email address in lowercase before validation runs.
     *
     * @return void
     */
    protected function normaliseAuthEmail(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => EmailAddress::normalise($this->string('email')->toString()),
            ]);
        }
    }
}
