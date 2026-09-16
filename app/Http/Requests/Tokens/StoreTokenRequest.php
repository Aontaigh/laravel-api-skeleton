<?php

declare(strict_types=1);

namespace App\Http\Requests\Tokens;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;
use App\Support\PresentingToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Validates and authorises a request to create a Token for the current User.
 */
final class StoreTokenRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ResolvesAuthenticatedViewer;
    use ValidatesTokenPayload;

    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * A scoped Personal Access Token must not be able to mint a broader one:
     * the new token defaults to a wildcard ability set, so allowing a scoped
     * token through this endpoint would let it escalate out of its own scope.
     * Cookie and unrestricted (`['*']`) callers are unaffected.
     *
     * @return bool true when the User may create their own Token
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user?->can('create', PersonalAccessToken::class) !== true) {
            return false;
        }

        $presenting = $user->currentAccessToken();

        return ! PresentingToken::isPersonalAccessToken($presenting) || $presenting->can('*');
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> the create-Token validation rules
     */
    public function rules(): array
    {
        return $this->tokenPayloadRules();
    }
}
