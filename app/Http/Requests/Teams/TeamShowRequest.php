<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Teams\AppliesTeamShowParams;
use App\Models\Team;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises Team show requests.
 */
final class TeamShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesTeamShowParams;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * @return bool true when the User may view the route-bound Team
     */
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return $team instanceof Team
            && $this->user()?->can('view', $team) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->teamShowRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateTeamShowParams($validator);
    }
}
