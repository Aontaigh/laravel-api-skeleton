<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\Exceptions\Auth\ServiceAccountAuthenticationException;
use App\Models\User;
use App\Support\AuthTimingHash;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Verifies email and password credentials for login.
 */
final class AuthenticateUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Optional lookup override for unit tests - the container always passes null.
     *
     * @param (Closure(string): ?User)|null $resolveUserByEmail optional User resolver
     */
    public function __construct(
        private ?Closure $resolveUserByEmail = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the User when credentials are valid.
     *
     * Uses a single generic validation message so callers cannot distinguish
     * missing accounts from wrong passwords. Runs a dummy password check when
     * the email is unknown so response timing does not reveal account existence.
     * A suspended account still returns here after a successful password check
     * so the controller can answer `403 Account Suspended` without collapsing
     * that case into the enumeration-safe 422.
     *
     * @example
     * app(AuthenticateUserAction::class)->execute($credentials);
     *
     * @param  LoginCredentialsData $credentials the login payload
     * @return User                 the authenticated User
     *
     * @throws ValidationException when credentials are invalid or the account is a service account
     */
    public function execute(LoginCredentialsData $credentials): User
    {
        $user = $this->findUserForEmail($credentials->email);

        if ($user === null) {
            Hash::check($credentials->password, $this->timingNormalisationHash());

            throw ValidationException::withMessages([
                'email' => ['Invalid Credentials'],
            ]);
        }

        if (! Hash::check($credentials->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid Credentials'],
            ]);
        }

        /*
         * A service account is a machine identity owned by an API client. It
         * authenticates only through the client-credentials exchange, so a
         * password sign-in is refused with the same generic message as a
         * wrong password to avoid disclosing that the account exists.
         */
        if ($user->isServiceAccount()) {
            throw ServiceAccountAuthenticationException::generic();
        }

        /*
         * A suspended account is returned rather than thrown: the controller
         * answers a named 403 `Account Suspended` because the caller proved
         * the password, so naming the state is the friendlier and clearer
         * signal - the same answer the active.account middleware gives
         * mid-session. A suspended MFA-enrolled account is refused before a
         * two-factor challenge is opened.
         *
         * Upgrade an out-of-date hash to the configured driver and work
         * factors, skipping the write for a suspended account: the record
         * cannot be used by this caller, so mutating it (and paying the
         * Argon2id cost) buys nothing.
         */
        if (! $user->isSuspended()
            && config()->boolean('hashing.rehash_on_login')
            && Hash::needsRehash($user->password)
        ) {
            $user->password = $credentials->password;
            $user->save();
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a User by email address.
     *
     * @param  string    $email the normalised email address
     * @return User|null the matching User, or null when no row exists
     */
    private function findUserForEmail(string $email): ?User
    {
        if ($this->resolveUserByEmail !== null) {
            return ($this->resolveUserByEmail)($email);
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $email)
            ->first();

        return $user;
    }

    /**
     * Return the hash used to normalise login timing for unknown emails.
     *
     * Generated at boot with the configured driver (argon2id), so the dummy
     * check costs the same as a real password verification.
     *
     * @return string the configured hash compared against when no User matches
     */
    private function timingNormalisationHash(): string
    {
        return AuthTimingHash::value();
    }
}
