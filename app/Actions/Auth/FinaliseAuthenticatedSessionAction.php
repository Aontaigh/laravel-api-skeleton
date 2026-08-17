<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\FinaliseAuthenticatedSessionData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;

/**
 * Applies remember-me state, issues a Sanctum token, and records the login audit.
 */
final class FinaliseAuthenticatedSessionAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new FinaliseAuthenticatedSessionAction.
     *
     * @param ApplyRememberMeAction           $applyRemember applies remember-me cookies and tokens
     * @param CreatePersonalAccessTokenAction $issueToken    issues the Sanctum bearer token
     */
    public function __construct(
        private readonly ApplyRememberMeAction $applyRemember,
        private readonly CreatePersonalAccessTokenAction $issueToken,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Issue a bearer token and record the successful sign-in.
     *
     * @param  FinaliseAuthenticatedSessionData $data the session finalisation payload
     * @return NewAccessToken                   the newly issued token
     */
    public function execute(FinaliseAuthenticatedSessionData $data): NewAccessToken
    {
        $user = $data->user;

        if ($data->remember) {
            $this->applyRemember->execute($user);
            Auth::guard('web')->login($user->refresh(), true);

            if ($data->regenerateSession) {
                session()->regenerate();
            }
        }

        $newToken = $this->issueToken->execute(new CreateTokenData(
            forUser: $user,
            name: $data->deviceName,
            abilities: ['*'],
            remember: $data->remember,
        ));

        if (session()->isStarted()) {
            session()->put('session_version', $user->session_version);
        }

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            userId: $user->id,
            email: $user->email,
            ipAddress: $data->ipAddress,
            userAgent: $data->userAgent,
            personalAccessTokenId: $newToken->accessToken->id,
            rememberMe: $data->remember,
        ));

        return $newToken;
    }
}
