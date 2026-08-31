<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\Models\User;
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
     * Optional lookup override for unit tests — the container always passes null.
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
     *
     * @example
     * app(AuthenticateUserAction::class)->execute($credentials);
     *
     * @param  LoginCredentialsData $credentials the login payload
     * @return User                 the authenticated User
     *
     * @throws ValidationException when credentials are invalid
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

        if ($user->isSuspended() || $user->isServiceAccount()) {
            throw ValidationException::withMessages([
                'email' => ['Invalid Credentials'],
            ]);
        }

        /*
         * Upgrade an out-of-date hash to the configured driver and work factors.
         * Placed after the suspension and service-account guards so only a
         * credential that would actually authenticate triggers the write: the
         * rehash on a suspended or service account would mutate a record the
         * caller can never use, and leak by timing that such an account exists.
         */
        if (config()->boolean('hashing.rehash_on_login') && Hash::needsRehash($user->password)) {
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
        return config()->string('api.auth_timing_normalisation_hash');
    }
}
