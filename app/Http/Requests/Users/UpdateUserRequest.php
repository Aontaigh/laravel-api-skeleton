<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Authorises and validates a request to update a User.
 */
final class UpdateUserRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------​
    | Traits
    |--------------------------------------------------------------------------​
    */

    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may update the route-bound User
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user instanceof User
            && $this->user()?->can('update', $user) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>> the update payload rules
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            'team_id' => [
                Rule::prohibitedIf(fn (): bool => $this->user()?->can('reassignTeam', User::class) !== true),
                'sometimes',
                'required',
                'integer',
                Rule::exists('teams', 'id'),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator the validator under construction
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasAny(['name', 'team_id'])) {
                return;
            }

            $validator->errors()->add(
                'name',
                'At Least One Field Is Required',
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['name'];
    }
}
