<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\NormalisesE164PhoneAttributes;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;
use App\Models\User;
use App\Rules\E164PhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Authorises and validates a request to update a User.
 */
final class UpdateUserRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use NormalisesE164PhoneAttributes;
    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * Two gates, both answered with `403 Forbidden` when they fail:
     *
     * - `update` on the route-bound User (the base write authorisation).
     * - A payload attempting a role change additionally requires the
     *   `assignRole` ability on that User. Managers hold `update` without
     *   `assign-role`, Admins are refused on themselves, and service
     *   accounts are immutable.
     * - A payload attempting a team reassignment requires the
     *   `reassignTeam` ability.
+     *
+     *   Both surface as authorisation (403) rather than a field-level
+     *   validation error (422): identity and permission problems belong in
+     *   the status the caller's retry logic expects.
     *
     * @return bool true when the User may update the route-bound User
     */
    public function authorize(): bool
    {
        /** @var User|null $target */
        $target = $this->route('user');

        if (! $target instanceof User) {
            return false;
        }

        if ($this->has('role') && $this->user()?->can('assignRole', $target) !== true) {
            return false;
        }

        if ($this->has('team_id') && $this->user()?->can('reassignTeam', User::class) !== true) {
            return false;
        }

        return $this->user()?->can('update', $target) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

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
            'team_id' => ['sometimes', 'required', 'integer', Rule::exists('teams', 'id')],
            'role' => [
                Rule::prohibitedIf(fn (): bool => $this->user()?->can('assignRole', $this->route('user')) !== true),
                'sometimes',
                'required',
                'string',
                Rule::in(RoleName::Admin->value, RoleName::Manager->value, RoleName::User->value),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', new E164PhoneNumber],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'The Selected Role Is Invalid',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Configure the validator instance.
     *
     * @param  Validator $validator the validator under construction
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasAny(['name', 'team_id', 'role', 'phone'])) {
                return;
            }

            $validator->errors()->add(
                'name',
                'At Least One Field Is Required',
            );
        });
    }
    /*
    |--------------------------------------------------------------------------
    | Preparation
    |--------------------------------------------------------------------------
    */

    /**
     * Prepare the data for validation.
     *
     * Sanitise display text first, then compact phone display forms to
     * canonical E.164 so the strict rule sees canonical input.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->sanitisePlainTextAttributes();
        $this->normaliseE164PhoneAttributes($this->e164PhoneAttributeKeys());
    }

    /*
    |--------------------------------------------------------------------------
    | Sanitisation
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     *
     * @return list<string> the attribute names to sanitise
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['name'];
    }

    /**
     * {@inheritDoc}
     *
     * @return list<string> the phone attribute names to compact before validation
     */
    protected function e164PhoneAttributeKeys(): array
    {
        return ['phone'];
    }
}
