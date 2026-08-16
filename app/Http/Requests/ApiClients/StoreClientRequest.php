<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiClients;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;
use App\Models\ApiClient;

/**
 * Validates and authorises API client creation requests.
 */
final class StoreClientRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ValidatesTokenPayload;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Only callers authorised to create API clients may store a client.
     *
     * @return bool whether the caller may create an API client
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ApiClient::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string'],
        ];
    }
}
