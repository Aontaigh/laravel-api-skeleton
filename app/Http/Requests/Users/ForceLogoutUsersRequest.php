<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;

/**
 * Authorises an admin request to force-logout Users by id.
 */
final class ForceLogoutUsersRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Maximum number of User ids accepted per request. */
    public const MAX_USER_IDS = 100;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to force-logout other Users.
     *
     * @return bool true when the User holds `users.force-logout`
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.force-logout') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Existence and service-account guardrails run in {@see withValidator()} so
     * every id is checked in a single query instead of one query per array item.
     *
     * @return array<string, array<int, mixed>> the force-logout rules
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_USER_IDS],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Reject ids that do not exist on a non-service account, including soft-deleted rows.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $check): void {
            if ($check->errors()->isNotEmpty()) {
                return;
            }

            /** @var mixed $rawIds */
            $rawIds = $this->input('ids');

            if (! is_array($rawIds)) {
                return;
            }

            /** @var array<int, int> $idsByIndex */
            $idsByIndex = [];

            foreach (array_keys($rawIds) as $index) {
                $value = $rawIds[$index];

                if (! is_int($value)) {
                    continue;
                }

                $idsByIndex[$index] = $value;
            }

            if ($idsByIndex === []) {
                return;
            }

            /** @var array<int, true> $validIdLookup */
            $validIdLookup = [];

            foreach (
                User::withTrashed()
                    ->whereIn('id', array_values($idsByIndex))
                    ->where('is_service_account', false)
                    ->pluck('id') as $id
            ) {
                if (is_int($id)) {
                    $validIdLookup[$id] = true;
                }
            }

            foreach ($idsByIndex as $index => $id) {
                if (! isset($validIdLookup[$id])) {
                    $check->errors()->add(
                        "ids.{$index}",
                        "The selected ids.{$index} is invalid.",
                    );
                }
            }
        });
    }
}
