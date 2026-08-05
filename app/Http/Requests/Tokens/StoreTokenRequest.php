<?php

declare(strict_types=1);

namespace App\Http\Requests\Tokens;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;
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
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may create their own Token
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', PersonalAccessToken::class) === true;
    }

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
