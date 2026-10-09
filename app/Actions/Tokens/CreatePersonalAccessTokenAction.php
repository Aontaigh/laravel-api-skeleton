<?php

declare(strict_types=1);

namespace App\Actions\Tokens;

use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Exceptions\InvalidTokenExpirationException;
use App\Services\Permissions\PermissionAbilityCatalog;
use App\Support\TokenLifetime;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues a new Sanctum Personal Access Token for a User.
 */
final class CreatePersonalAccessTokenAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Create Personal Access Token Action.
     *
     * @param PermissionAbilityCatalog $abilityCatalog validates abilities against the permission catalog
     */
    public function __construct(
        private readonly PermissionAbilityCatalog $abilityCatalog,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a Personal Access Token from the given data.
     *
     * Every issued token carries an expiry: a caller-chosen `null` (never
     * expires) and any expiry beyond `token_max_expiration_days` are refused,
     * and a configured lifetime is bounded to the same ceiling.
     *
     * @example
     * $token = app(CreatePersonalAccessTokenAction::class)->execute($data);
     *
     * @param  CreateTokenData $data the Token payload
     * @return NewAccessToken  the newly issued Token, including its one-time plaintext value
     *
     * @throws InvalidTokenExpirationException when the requested expiry is missing or beyond the maximum
     */
    public function execute(CreateTokenData $data): NewAccessToken
    {
        $abilities = $this->abilityCatalog->normalizeTokenAbilities($data->abilities);

        return $data->forUser->createToken(
            $data->name,
            $abilities,
            $this->resolveExpiresAt($data),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the token's expiration, refusing a non-expiring or over-long lifetime.
     *
     * A caller-supplied expiry (`useConfiguredExpiration` false) is bounded by
     * refusal so the caller learns its request was rejected; a configured
     * lifetime is bounded by capping so a misconfiguration cannot mint a token
     * that outlives the policy or, at zero, never expires at all.
     *
     * @param  CreateTokenData $data the Token payload
     * @return Carbon          the token expiry, always in the future and within the ceiling
     */
    private function resolveExpiresAt(CreateTokenData $data): Carbon
    {
        if ($data->useConfiguredExpiration) {
            $days = $data->remember
                ? config()->integer('api.remember_token_expiration_days')
                : config()->integer('api.token_expiration_days');

            return now()->addDays(TokenLifetime::boundedConfiguredDays($days));
        }

        $expiresAt = $data->expiresAt;

        if ($expiresAt === null) {
            throw InvalidTokenExpirationException::nonExpiring();
        }

        $maximumDays = TokenLifetime::maximumDays();

        if ($expiresAt->greaterThan(now()->addDays($maximumDays))) {
            throw InvalidTokenExpirationException::beyondMaximum($maximumDays);
        }

        return $expiresAt;
    }
}
