<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiClients;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ApiClients\AppliesApiClientShowParams;
use App\Models\ApiClient;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises API client show requests.
 */
final class ClientShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesApiClientShowParams;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * @return bool true when the User may view the route-bound API client
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof ApiClient
            && $this->user()?->can('view', $client) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->apiClientShowRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    public function withValidator(Validator $validator): void
    {
        $this->validateApiClientShowParams($validator);
    }
}
