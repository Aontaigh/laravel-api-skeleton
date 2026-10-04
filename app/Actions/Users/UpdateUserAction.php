<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Auth\RevokePasswordResetTokensAction;
use App\DataTransferObjects\Users\UpdateUserData;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Updates a User's attributes.
 */
final class UpdateUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Update User Action.
     *
     * @param RevokePasswordResetTokensAction $revokePasswordResetTokens deletes outstanding reset tokens on a role change
     */
    public function __construct(
        private readonly RevokePasswordResetTokensAction $revokePasswordResetTokens,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the validated changes and return the refreshed User.
     *
     * @example
     * app(UpdateUserAction::class)->execute($data);
     *
     * @param  UpdateUserData $data the validated update payload
     * @return User           the refreshed User
     */
    public function execute(UpdateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $attributes = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
            }

            if ($data->teamId !== null) {
                $attributes['team_id'] = $data->teamId;
            }

            if ($data->phone !== null) {
                $attributes['phone'] = $data->phone;
            }

            $data->user->update($attributes);

            if ($data->role !== null) {
                $this->guardLastAdminDemotion($data->user, $data->role);

                /*
                 * A role change is a privilege change, so any outstanding
                 * password reset link is revoked in the same transaction: a
                 * link requested while the account was an interactive user
                 * stays consumable after the account becomes a machine
                 * identity - a pending credential-grant surface must not
                 * outlive the role it was issued against. Microsoft Entra
                 * instructs administrators to revoke refresh tokens alongside
                 * role and state changes in the same operation, and Google
                 * Cloud IAM draws the same boundary from the other side: a
                 * service account has no password at all, so the interactive
                 * credential surface must not survive the transition.
                 *
                 * The revocation is scoped to an actual role change: a direct
                 * API consumer may round-trip the account's existing role,
                 * and that no-op is not a privilege change. Revoking then
                 * would silently break a pending reset for a caller doing
                 * nothing, which is why the check is `hasRole` rather than
                 * mere presence of the field. GitHub and Stripe both scope
                 * credential invalidation to a real security-state change.
                 *
                 * The role assignment itself sits *outside* that guard, and
                 * that split is deliberate. `syncRoles` is what makes this
                 * endpoint a set rather than an add: it is the only thing
                 * that drops a role the caller did not ask to keep. Guarding
                 * it meant a round-trip returned 200 while leaving a
                 * multi-role user holding roles the response denied - and a
                 * multi-role user is reachable, because `CreateUserAction`
                 * and `CreateApiClientAction` both use `assignRole`, which
                 * adds without removing. Revocation asks "did a privilege
                 * change?"; assignment asks "what is the role set now?", and
                 * only the first depends on the prior state.
                 */
                $requestedRoles = [$data->role->value];

                /*
                 * Revocation asks whether the *set* changes, not whether the
                 * requested role is present. `hasRole` answers membership, so a user
                 * holding `user` and `manager` who re-sends `manager` passed it while
                 * `syncRoles` below still dropped `user` - a real reduction in
                 * privilege with the pending reset link left valid. Comparing the
                 * complete sorted set against the single requested role keeps a
                 * genuine no-op a no-op, so
                 * `it_keeps_an_outstanding_reset_token_when_the_role_is_unchanged`
                 * still holds.
                 */
                $currentRoles = $data->user->getRoleNames()->sort()->values()->all();

                if ($currentRoles !== $requestedRoles) {
                    $this->revokePasswordResetTokens->execute($data->user);
                }

                $data->user->syncRoles($requestedRoles);
            }

            return $data->user->refresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Refuse a role change that would leave no Admin in the system.
     *
     * Demoting the last Admin is irreversible through the API - no remaining
     * account could promote a replacement - so it answers `422` while the
     * Policy answers `403` for who may assign roles at all. Soft-deleted
     * accounts never count: the Spatie role scope honours the SoftDeletes
     * global scope, so a deleted Admin cannot keep the seat warm.
     *
     * @param  User     $user    the User whose role would change
     * @param  RoleName $newRole the requested role
     * @return void
     *
     * @throws ValidationException when the change would remove the last Admin
     */
    private function guardLastAdminDemotion(User $user, RoleName $newRole): void
    {
        if ($newRole === RoleName::Admin || ! $user->hasRole(RoleName::Admin)) {
            return;
        }

        $anotherAdminExists = User::query()
            ->role(RoleName::Admin->value)
            ->where('id', '!=', $user->id)
            ->exists();

        if (! $anotherAdminExists) {
            throw ValidationException::withMessages([
                'role' => ['Cannot Demote The Last Admin'],
            ]);
        }
    }
}
