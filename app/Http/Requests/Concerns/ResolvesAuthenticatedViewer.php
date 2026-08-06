<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Narrows the authenticated guard user to the concrete `App\Models\User`.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ResolvesAuthenticatedViewer
{
    /*
    |--------------------------------------------------------------------------
    | Abstract
    |--------------------------------------------------------------------------
    */

    /**
     * Get the authenticated user for the request.
     *
     * Declared abstractly, like the accessors in `ReadsRequestInput`, so the
     * host FormRequest satisfies a contract PHP checks rather than a
     * docblock. The signature must match Laravel's exactly.
     *
     * @param  string|null          $guard the auth guard to resolve against
     * @return Authenticatable|null the authenticated user, or null when a guest
     */
    abstract public function user($guard = null);

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the authenticated User making the request.
     *
     * `authorize()` has already run by the time accessors are called, so a
     * missing User is a wiring fault (route not behind auth) rather than a
     * client error — fail loudly instead of degrading silently.
     *
     * @return User the authenticated User
     *
     * @throws AuthenticationException when the request is unauthenticated
     */
    public function viewer(): User
    {
        $viewer = $this->user();

        if (! $viewer instanceof User) {
            throw new AuthenticationException;
        }

        return $viewer;
    }
}
