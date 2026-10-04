<?php

declare(strict_types=1);

namespace App\Actions\ApiClients;

use App\DataTransferObjects\ApiClients\UpdateApiClientData;
use App\Models\ApiClient;
use App\Services\Permissions\PermissionAbilityCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Updates an API client's mutable fields and syncs the service User name.
 */
final class UpdateApiClientAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UpdateApiClientAction.
     *
     * @param PermissionAbilityCatalog $abilityCatalog validates abilities against the permission catalog
     */
    public function __construct(
        private readonly PermissionAbilityCatalog $abilityCatalog,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the requested updates to the API client.
     *
     * @param  ApiClient           $client the client to update
     * @param  UpdateApiClientData $data   the validated update payload
     * @return ApiClient           the refreshed client
     */
    public function execute(ApiClient $client, UpdateApiClientData $data): ApiClient
    {
        $attributes = [];
        $previousAbilities = $client->abilities;

        if ($data->name !== null) {
            $attributes['name'] = $data->name;
        }

        if ($data->abilities !== null) {
            /*
             * Machine identities must be scoped: the wildcard is refused for
             * API clients (human-side tokens keep `['*']` semantics by design).
             */
            $attributes['abilities'] = $this->abilityCatalog->normalizeApiClientTokenAbilities($data->abilities);
        }

        if ($data->isActive !== null) {
            $attributes['is_active'] = $data->isActive;
        }

        DB::transaction(function () use ($client, $attributes, $previousAbilities): void {
            if ($attributes !== []) {
                $client->forceFill($attributes)->save();
            }

            /*
             * Any abilities change must end the live access of outstanding
             * tokens: a token carries the abilities it was minted with, so a
             * narrowing would keep exercising powers just removed, and a
             * broadening would sit inert - the new grant unreachable until
             * the stale token's expiry. Revoking forces the integration to
             * re-exchange under the current scope either way; deactivation
             * has the same effect and additionally refuses new exchanges.
             */
            if (isset($attributes['abilities']) && $this->abilitiesChanged($attributes['abilities'], $previousAbilities)) {
                $client->user->tokens()->delete();
            }

            /*
             * Deactivating a compromised integration must end its live access
             * immediately: `is_active` is only consulted at exchange time, so
             * an already-issued bearer token would otherwise keep the full
             * Service-role power until its 30-day expiry.
             */
            if (($attributes['is_active'] ?? null) === false) {
                $client->user->tokens()->delete();
            }

            /*
             * When the client name changes, keep the linked service
             * User name in sync for audit-log readability.
             */
            if (isset($attributes['name'])) {
                $client->user->forceFill(['name' => $attributes['name']])->save();
            }
        });

        return $client->refresh();
    }

    /**
     * Whether the new ability list differs from the previous one.
     *
     * Ability lists are unordered sets: a re-submission in a different order
     * with the same values changes nothing an issued token can reach, so the
     * comparison normalises both sides before the strict check.
     *
     * @param  list<string> $candidate the newly requested abilities
     * @param  list<string> $previous  the abilities stored before the update
     * @return bool         true when the sets differ and tokens must be revoked
     */
    private function abilitiesChanged(array $candidate, array $previous): bool
    {
        return $this->normalisedAbilitySet($candidate) !== $this->normalisedAbilitySet($previous);
    }

    /**
     * @param  list<string> $abilities
     * @return list<string>
     */
    private function normalisedAbilitySet(array $abilities): array
    {
        $unique = [];

        foreach ($abilities as $ability) {
            if (! in_array($ability, $unique, true)) {
                $unique[] = $ability;
            }
        }

        sort($unique);

        return $unique;
    }
}
