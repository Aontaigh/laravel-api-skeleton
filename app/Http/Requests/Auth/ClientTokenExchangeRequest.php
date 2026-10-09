<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Exceptions\Auth\OAuthTokenRequestException;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Validates and authorises client-credentials token exchange.
 */
final class ClientTokenExchangeRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    /**
     * Token exchange is open to unauthenticated callers.
     *
     * @return bool always true
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'grant_type' => ['required', 'string', Rule::in(['client_credentials'])],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Failed Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Fail with the RFC 6749 wire codes instead of a `422` validation envelope.
     *
     * Standard: [RFC 6749 section 5.2](https://www.rfc-editor.org/rfc/rfc6749#section-5.2) requires
     * the token endpoint to answer `400` with a top-level `error` field. A conformant OAuth client
     * reads that field to decide whether to retry, re-authenticate, or stop, so a `422` carrying
     * `meta.errors` makes this endpoint unusable to any standard client library even though the
     * envelope is right everywhere else on this API.
     *
     * Each rule failure maps to the code the RFC defines for it, rather than collapsing everything
     * into one code: a missing parameter is `invalid_request`, and an unsupported grant type is
     * `unsupported_grant_type`. A client that sends `grant_type=password` deserves to be told the
     * grant is unsupported, not that a parameter is malformed.
     *
     * `ValidationException` is caught and re-thrown by the controller purely to attach an audit
     * row, so the substituted exception still lands in the same `catch` block and is still recorded.
     *
     * @throws OAuthTokenRequestException always, on any validation failure
     */
    protected function failedValidation(Validator $validator): void
    {
        /*
         * Name the parameter the validator actually rejected, not a guessed one. A hardcoded
         * fallback misreports the common case: an oversized `client_secret` with a perfectly valid
         * `client_id` would be described as `Missing Or Invalid Parameter: client_id`, sending the
         * caller to fix the one field it already got right. `errors()->keys()` is the authoritative
         * list, so the description always names a field that genuinely failed.
         *
         * `grant_type` is resolved first because it is the one parameter whose failure maps to a
         * different code. `input()` is mixed, so it is narrowed before use: a blank, absent, or
         * array-valued `grant_type` is malformed input and reports `invalid_request`, while only a
         * well-formed string naming a grant this endpoint does not implement reports
         * `unsupported_grant_type`. A well-formed `client_credentials` reaching that final throw
         * could only mean the value was tampered with after validation ran, so it reports
         * `invalid_request` rather than claiming the endpoint cannot do the grant it supports.
         */
        if ($validator->errors()->has('grant_type')) {
            $grantType = $this->input('grant_type');

            if (! is_string($grantType) || trim($grantType) === '') {
                throw OAuthTokenRequestException::invalidRequest('grant_type');
            }

            if ($grantType !== 'client_credentials') {
                throw OAuthTokenRequestException::unsupportedGrantType($grantType);
            }

            throw OAuthTokenRequestException::invalidRequest('grant_type');
        }

        $failed = $validator->errors()->keys();

        throw OAuthTokenRequestException::invalidRequest($failed[0]);
    }
}
