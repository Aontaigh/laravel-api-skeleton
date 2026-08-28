<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Authorises and validates a self-service profile update request.
 */
final class UpdateMeRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Any interactive authenticated User may update their own profile.
     *
     * @return bool true when the caller may update their profile via `PATCH /me`
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('updateMe', $user);
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            'team_id' => ['prohibited'],
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
     * @param Validator $validator the validator under construction
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->safe()->has('name')) {
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
}
