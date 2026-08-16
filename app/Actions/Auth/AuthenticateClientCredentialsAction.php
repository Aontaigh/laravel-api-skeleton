<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\ClientCredentialsData;
use App\Models\ApiClient;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Verifies API client credentials for the client-credentials grant.
 */
final class AuthenticateClientCredentialsAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param (Closure(string): ?ApiClient)|null $resolveClientById optional client resolver for tests
     */
    public function __construct(
        private ?Closure $resolveClientById = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the ApiClient when credentials are valid.
     *
     * Uses a single generic validation message so callers cannot distinguish
     * missing clients from wrong secrets.
     *
     * @example
     * app(AuthenticateClientCredentialsAction::class)->execute($credentials);
     *
     * @param  ClientCredentialsData $credentials the client id and secret payload
     * @return ApiClient             the authenticated client row
     *
     * @throws ValidationException when credentials are invalid
     */
    public function execute(ClientCredentialsData $credentials): ApiClient
    {
        $client = $this->findActiveClient($credentials->clientId);

        if ($client === null) {
            Hash::check($credentials->clientSecret, $this->timingNormalisationHash());

            throw ValidationException::withMessages([
                'client_id' => ['Invalid Credentials'],
            ]);
        }

        if (! Hash::check($credentials->clientSecret, $client->client_secret)) {
            throw ValidationException::withMessages([
                'client_id' => ['Invalid Credentials'],
            ]);
        }

        $user = $client->getRelationValue('user');

        if (! $user instanceof User || $user->trashed() || $user->isSuspended()) {
            throw ValidationException::withMessages([
                'client_id' => ['Invalid Credentials'],
            ]);
        }

        return $client;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Find an active client by public client id.
     *
     * @param  string         $clientId the public client identifier
     * @return ApiClient|null the matching client, or null when not found or inactive
     */
    private function findActiveClient(string $clientId): ?ApiClient
    {
        if ($this->resolveClientById !== null) {
            $client = ($this->resolveClientById)($clientId);

            if ($client !== null) {
                $client->loadMissing('user');
            }

            return $client;
        }

        /** @var ApiClient|null $client */
        $client = ApiClient::query()
            ->with('user')
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();

        return $client;
    }

    /**
     * Return the bcrypt hash used to normalise timing for unknown client ids.
     *
     * @return string the configured bcrypt hash compared against when no API Client matches
     */
    private function timingNormalisationHash(): string
    {
        return config()->string('api.auth_timing_normalisation_hash');
    }
}
