<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns\Auth;

/**
 * Reads the optional opaque two-factor token for stateless clients.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ResolvesTwoFactorPending
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the opaque pending-challenge token when the client sent one.
     *
     * @return string|null the token, or null when omitted
     */
    public function twoFactorToken(): ?string
    {
        if (! $this->safe()->has('two_factor_token')) {
            return null;
        }

        $token = $this->safe()->string('two_factor_token')->toString();

        return $token !== '' ? $token : null;
    }
}
