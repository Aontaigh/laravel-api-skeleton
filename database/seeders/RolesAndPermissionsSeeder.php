<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the Spatie roles and permissions the API authorises against.
 *
 * `PERMISSIONS` is the complete allow-list of permission strings this application
 * defines. `ROLE_PERMISSIONS` maps each {@see RoleName} to the subset it receives.
 *
 * Full reference (what each permission gates, where it is enforced, role matrix):
 * `docs/permissions.md`.
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Every permission string this application defines.
     *
     * @see docs/permissions.md for descriptions and enforcement locations
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'users.list',              // GET /api/users
        'users.list-all',          // cross-team row scope on the user index
        'users.view-email',        // email column visibility on user records
        'users.update',            // PATCH /api/users/{user}
        'users.reassign-team',     // team_id changes on PATCH /api/users/{user}
        'users.delete',            // DELETE /api/users/{user} (soft delete)
        'roles.list',              // GET /api/roles and GET /api/roles/{role}
        'tokens.list-own',         // GET /api/tokens (own tokens only)
        'tokens.create-own',       // POST /api/tokens
        'tokens.revoke-own',       // DELETE /api/tokens/{token} (own tokens only)
        'tokens.create-for-user',  // POST /api/users/{user}/tokens
    ];

    /**
     * Role name mapped to the permissions granted at seed time.
     *
     * @see docs/permissions.md for the role matrix
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        RoleName::Admin->value => [
            'users.list',
            'users.list-all',
            'users.view-email',
            'users.update',
            'users.reassign-team',
            'users.delete',
            'roles.list',
            'tokens.list-own',
            'tokens.create-own',
            'tokens.revoke-own',
            'tokens.create-for-user',
        ],
        RoleName::Manager->value => [
            'users.list',
            'users.update',
            'users.delete',
            'roles.list',
            'tokens.list-own',
            'tokens.create-own',
            'tokens.revoke-own',
        ],
        RoleName::User->value => [
            'tokens.list-own',
            'tokens.create-own',
            'tokens.revoke-own',
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Run the seeder.
     *
     * Resets the cached permission collection first so re-running the
     * seeder in the same process (e.g. `RefreshDatabase` in tests) never
     * assigns a role a permission ID from a row that migration already
     * dropped.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::findOrCreate($roleName)->syncPermissions($permissions);
        }
    }
}
