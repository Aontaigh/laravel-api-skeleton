<?php

declare(strict_types=1);

namespace App\Http\Requests\Tokens;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ResolvesAuthenticatedViewer;
use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;
use Illuminate\Support\Carbon;
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
     * The presenting-token scope guard (a scoped PAT cannot mint a token
     * broader than itself) lives in `PersonalAccessTokenPolicy::create`, which
     * reads the presenting token from the authenticated User so a bare `can()`
     * check cannot omit it.
     *
     * @return bool true when the User may create their own Token
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', PersonalAccessToken::class) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the payload sets an expiry explicitly, including an explicit null.
     *
     * Laravel distinguishes a missing key from an explicit `null`: omitting
     * `expires_at` keeps the configured default lifetime, while sending it as
     * `null` opts the Token into never expiring.
     *
     * @return bool true when the caller set `expires_at` themselves
     */
    public function hasExplicitExpiry(): bool
    {
        return $this->safe()->has('expires_at');
    }

    /**
     * The requested expiry, or null for the configured default or a never-expires Token.
     *
     * @return Carbon|null the parsed expiry, or null
     */
    public function expiresAt(): ?Carbon
    {
        if (! $this->hasExplicitExpiry()) {
            return null;
        }

        $value = $this->validated('expires_at');

        return is_string($value) ? Carbon::parse($value) : null;
    }

    /**
     * Whether a null expiry should fall back to the configured default lifetime.
     *
     * @return bool true when the caller omitted `expires_at`
     */
    public function useConfiguredExpiration(): bool
    {
        return ! $this->hasExplicitExpiry();
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * `expires_at` is self-service only: the admin-issued Token path keeps
     * the configured lifetime, so the shared `tokenPayloadRules()` stays
     * untouched.
     *
     * @return array<string, array<int, string>> the create-Token validation rules
     */
    public function rules(): array
    {
        return [
            ...$this->tokenPayloadRules(),
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
