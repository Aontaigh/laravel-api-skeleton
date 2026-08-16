<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiClients;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;
use App\Models\ApiClient;

/**
 * Validates and authorises API client update requests.
 */
final class UpdateClientRequest extends ApiFormRequest
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
     * Only callers authorised to update the route-bound client may do so.
     *
     * @return bool whether the caller may update the API client
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof ApiClient
            && $this->user()?->can('update', $client) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'abilities.min' => 'Abilities Must Not Be Empty',
        ];
    }

    /**
     * Reject an entirely empty request body.
     *
     * At least one of `name`, `abilities`, or `is_active` must be present.
     *
     * @return list<\Closure(): void>
     */
    public function after(): array
    {
        return [
            function (): void {
                if (! $this->has('name') && ! $this->has('abilities') && ! $this->has('is_active')) {
                    $this->validator->errors()->add(
                        'name',
                        'At Least One of Name, Abilities, or Is Active Is Required'
                    );
                }
            },
        ];
    }
}
