<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Teams\AppliesTeamFilters;
use App\Models\Team;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises Team index requests.
 */
final class TeamIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesTeamFilters;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * @return bool true when the User may list Teams
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Team::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->teamFilterRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateTeamFilterKeys($validator);
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'teams');
        $this->validateSortQueryParam($validator);
    }
}
