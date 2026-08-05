<?php

declare(strict_types=1);

namespace App\Http\Requests\Tokens;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Tokens\AppliesTokenFilters;
use Illuminate\Contracts\Validation\Validator;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Validates and authorises Token Index requests.
 */
final class TokenIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesTokenFilters;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may list their own Tokens
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PersonalAccessToken::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the Token Index validation rules
     */
    public function rules(): array
    {
        return $this->tokenFilterRules();
    }

    /**
     * Run allow-list validation for filter, sort, include, and fields params.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateFilterKeys($validator);
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'tokens');
        $this->validateSortQueryParam($validator);
        $this->validateIncludeQueryParam($validator);
    }
}
