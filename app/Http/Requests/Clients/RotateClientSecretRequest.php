<?php

declare(strict_types=1);

namespace App\Http\Requests\Clients;

use App\Http\Requests\ApiFormRequest;
use App\Models\ApiClient;

/**
 * Authorises a request to rotate an API client's secret.
 */
final class RotateClientSecretRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * Rotation is an update of the client.
     *
     * @return bool true when the User may update the route-bound client
     */
    public function authorize(): bool
    {
        /** @var ApiClient|null $client */
        $client = $this->route('client');

        return $client instanceof ApiClient
            && $this->user()?->can('update', $client) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> no request body is accepted
     */
    public function rules(): array
    {
        return [];
    }
}
