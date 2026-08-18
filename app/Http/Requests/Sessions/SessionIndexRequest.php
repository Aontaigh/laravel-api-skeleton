<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Sessions\AppliesSessionFilters;
use App\Models\WebSession;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises Web Session Index requests.
 */
final class SessionIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesSessionFilters;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may list web sessions
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WebSession::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the Session Index validation rules
     */
    public function rules(): array
    {
        return $this->sessionFilterRules();
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
        $this->validateFieldsQueryParam($validator, 'sessions');
        $this->validateFieldsQueryParam($validator, 'users');
        $this->validateSortQueryParam($validator);
        $this->validateIncludeQueryParam($validator);
    }
}
