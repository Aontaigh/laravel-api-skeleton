<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiClients;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ApiClients\AppliesClientFilters;
use App\Models\ApiClient;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises API client index requests.
 */
final class ClientIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesClientFilters;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Only callers authorised to list API clients may index clients.
     *
     * @return bool whether the caller may list API clients
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ApiClient::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->clientFilterRules();
    }

    /**
     * Apply additional validation after the base rules pass.
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateFilterKeys($validator);
        $this->validateFieldsKeys($validator);
        $this->validateIncludeQueryParam($validator);
        $this->validateFieldsQueryParam($validator, 'api_clients');
        $this->validateSortQueryParam($validator);
    }
}
