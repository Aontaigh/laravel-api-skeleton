<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for the load-balancer health probe.
 *
 * Takes no input - the controller returns a static database and version
 * snapshot. The request class exists for signature consistency, per the
 * every-controller-takes-a-FormRequest rule.
 */
final class ShowHealthRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool always true; the Health probe is public by design
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> an empty rule set; the probe takes no input
     */
    public function rules(): array
    {
        return [];
    }
}
