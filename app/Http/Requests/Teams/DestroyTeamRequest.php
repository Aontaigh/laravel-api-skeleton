<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Http\Requests\ApiFormRequest;
use App\Models\Team;

/**
 * Authorises a request to delete a Team.
 */
final class DestroyTeamRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may delete the route-bound Team
     */
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return $team instanceof Team
            && $this->user()?->can('delete', $team) === true;
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
