<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Exceptions\InvalidTokenAbilitiesException;
use App\Queries\Roles\RoleQueryConstraints;
use Closure;
use Spatie\Permission\Models\Permission;

/**
 * Reads and validates Sanctum Token abilities against the application's
 * Spatie permission catalog.
 *
 * FormRequests validate the HTTP shape (`abilities` is an array of strings);
 * this Service enforces domain rules — only registered permission names (or
 * the unrestricted wildcard) may be stored on a Token.
 */
final class PermissionAbilityCatalog
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Ability value that grants every permission Sanctum checks for. */
    private const WILDCARD_ABILITY = '*';

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Permission Ability Catalog.
     *
     * @param Closure(): list<string>|null $nameResolver optional override for unit tests
     */
    public function __construct(
        private readonly ?Closure $nameResolver = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * List every permission name registered for the application's default guard.
     *
     * @return list<string> the permission names from the database
     */
    public function allNames(): array
    {
        if ($this->nameResolver !== null) {
            return ($this->nameResolver)();
        }

        /** @var list<string> $names */
        $names = Permission::query()
            ->where('guard_name', RoleQueryConstraints::GUARD_NAME)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names;
    }

    /**
     * Whether a permission name exists in the catalog.
     *
     * @param  string $name the ability or permission name to check
     * @return bool   true when the name is registered
     */
    public function exists(string $name): bool
    {
        return in_array($name, $this->allNames(), true);
    }

    /**
     * Whether the ability list grants unrestricted access.
     *
     * @param  list<string> $abilities the requested Token abilities
     * @return bool         true when the list contains only the wildcard
     */
    public function isUnrestricted(array $abilities): bool
    {
        return $abilities === [self::WILDCARD_ABILITY];
    }

    /**
     * Normalise and validate Token abilities before persistence.
     *
     * Accepts the unrestricted wildcard (`['*']`) or a list of registered
     * permission names. Rejects empty lists, unknown names, and wildcard lists
     * mixed with explicit permissions.
     *
     * @param  list<string> $requested the abilities from the validated request
     * @return list<string> the abilities safe to pass to Sanctum
     *
     * @throws InvalidTokenAbilitiesException when the list is empty, mixed, or unknown
     */
    public function normalizeTokenAbilities(array $requested): array
    {
        if ($requested === []) {
            throw new InvalidTokenAbilitiesException(['(empty)']);
        }

        if (in_array(self::WILDCARD_ABILITY, $requested, true)) {
            if (! $this->isUnrestricted($requested)) {
                throw new InvalidTokenAbilitiesException($requested);
            }

            return [self::WILDCARD_ABILITY];
        }

        $invalid = [];
        $normalized = [];

        foreach ($requested as $ability) {
            if ($this->exists($ability)) {
                $normalized[] = $ability;

                continue;
            }

            $invalid[] = $ability;
        }

        if ($invalid !== []) {
            throw new InvalidTokenAbilitiesException($invalid);
        }

        return array_values(array_unique($normalized));
    }
}
