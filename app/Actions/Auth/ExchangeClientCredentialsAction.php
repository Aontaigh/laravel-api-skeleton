<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\ClientCredentialsData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Enums\AuthAuditEvent;
use App\Models\ApiClient;
use Laravel\Sanctum\NewAccessToken;

/**
 * Exchanges client credentials for a scoped Sanctum bearer token.
 */
final class ExchangeClientCredentialsAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param AuthenticateClientCredentialsAction $authenticate verifies client id and secret
     * @param CreatePersonalAccessTokenAction     $issueToken   issues the scoped Sanctum token
     * @param RecordAuthAuditAction               $audit        records exchange audit events
     */
    public function __construct(
        private readonly AuthenticateClientCredentialsAction $authenticate,
        private readonly CreatePersonalAccessTokenAction $issueToken,
        private readonly RecordAuthAuditAction $audit,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Authenticate the client and issue a bearer token for its service User.
     *
     * @example
     * app(ExchangeClientCredentialsAction::class)->execute($credentials, $ip, $userAgent);
     *
     * @param  ClientCredentialsData                           $credentials the client id and secret payload
     * @param  string|null                                     $ipAddress   the caller IP address
     * @param  string|null                                     $userAgent   the caller user agent
     * @return array{client: ApiClient, token: NewAccessToken} the client row and issued token
     */
    public function execute(
        ClientCredentialsData $credentials,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $client = $this->authenticate->execute($credentials);
        $user = $client->user;

        $days = config()->integer('api.client_token_expiration_days');
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        $newToken = $this->issueToken->execute(new CreateTokenData(
            forUser: $user,
            name: $client->name.' Client Credentials',
            abilities: $client->abilities,
            expiresAt: $expiresAt,
        ));

        $client->forceFill(['last_used_at' => now()])->save();

        $this->audit->execute(new RecordAuthAuditData(
            event: AuthAuditEvent::ClientTokenExchange,
            userId: $user->id,
            email: $user->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            personalAccessTokenId: $newToken->accessToken->id,
            apiClientId: $client->id,
        ));

        return [
            'client' => $client->refresh(),
            'token' => $newToken,
        ];
    }
}
