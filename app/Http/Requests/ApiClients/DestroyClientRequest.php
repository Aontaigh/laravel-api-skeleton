<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiClients;

use App\Http\Requests\ApiFormRequest;
use App\Models\ApiClient;

/**
 * Authorises API client revocation requests.
 */
final class DestroyClientRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Only callers authorised to delete the route-bound client may revoke it.
     *
     * @return bool whether the caller may delete the API client
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof ApiClient
            && $this->user()?->can('delete', $client) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Revocation has no body fields to validate.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
