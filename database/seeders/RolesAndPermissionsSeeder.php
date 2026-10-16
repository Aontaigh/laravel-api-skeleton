<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Guard;
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
     * Serialises concurrent reseeds of the same matrix.
     *
     * Excluding anything requires a store every process shares - `database` and `redis` do; the
     * `array` store does not, being per-process.
     */
    public const LOCK_NAME = 'roles-and-permissions-seeder';

    /**
     * How long the lock may be held before the store releases it on its own.
     *
     * The write phase takes well under a second, so this only guards a process that died before its
     * `finally` ran: without a duration Laravel holds the lock effectively forever and every later
     * reseed would return without repairing the matrix.
     */
    public const LOCK_TTL = 300;

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
        'users.assign-role',       // role changes on PATCH /api/users/{user}
        'users.reassign-team',     // team_id changes on PATCH /api/users/{user}
        'users.delete',            // DELETE /api/users/{user} (soft delete)
        'users.restore',           // POST /api/users/{user}/restore
        'users.force-logout',      // POST /api/users/logout (admin session termination)
        'users.suspend',           // POST /api/users/{user}/suspend and /unsuspend
        'users.create',            // POST /api/users
        'roles.list',              // GET /api/roles and GET /api/roles/{role}
        'tokens.list-own',         // GET /api/tokens (own tokens only)
        'tokens.create-own',       // POST /api/tokens
        'tokens.revoke-own',       // DELETE /api/tokens/{token} (own tokens only)
        'tokens.create-for-user',  // POST /api/users/{user}/tokens
        'api-clients.list',        // GET /api/clients and GET /api/clients/{client}
        'api-clients.create',      // POST /api/clients
        'api-clients.delete',      // DELETE /api/clients/{client}
        'api-clients.update',      // PATCH /api/clients/{client}
        'webhooks.list',           // GET /api/webhook-endpoints
        'webhooks.create',         // POST /api/webhook-endpoints
        'webhooks.update',         // PATCH /api/webhook-endpoints/{endpoint}
        'webhooks.delete',         // DELETE /api/webhook-endpoints/{endpoint}
        'audit-logs.list',         // GET /api/audit-logs
        'teams.list',              // GET /api/teams and GET /api/teams/{team}
        'teams.create',            // POST /api/teams
        'teams.update',            // PATCH /api/teams/{team}
        'teams.delete',            // DELETE /api/teams/{team}
        'permissions.list',        // GET /api/permissions
        'sessions.list-own',       // GET /api/sessions (own sessions only)
        'sessions.list-all',       // cross-user row scope on the session index
        'sessions.revoke-own',     // DELETE /api/sessions/{web_session} and /api/sessions/current
        'sessions.revoke-any',     // DELETE /api/sessions/{web_session} for any User
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
            'users.assign-role',
            'users.reassign-team',
            'users.delete',
            'users.restore',
            'users.force-logout',
            'users.suspend',
            'users.create',
            'roles.list',
            'tokens.list-own',
            'tokens.create-own',
            'tokens.revoke-own',
            'tokens.create-for-user',
            'api-clients.list',
            'api-clients.create',
            'api-clients.delete',
            'api-clients.update',
            'webhooks.list',
            'webhooks.create',
            'webhooks.update',
            'webhooks.delete',
            'audit-logs.list',
            'teams.list',
            'teams.create',
            'teams.update',
            'teams.delete',
            'permissions.list',
            'sessions.list-own',
            'sessions.list-all',
            'sessions.revoke-own',
            'sessions.revoke-any',
        ],
        RoleName::Manager->value => [
            'users.list',
            'users.update',
            'users.delete',
            'roles.list',
            'tokens.list-own',
            'tokens.create-own',
            'tokens.revoke-own',
            'teams.list',
            'permissions.list',
            'sessions.list-own',
            'sessions.revoke-own',
        ],
        RoleName::User->value => [
            'tokens.list-own',
            'tokens.create-own',
            'tokens.revoke-own',
            'permissions.list',
            'sessions.list-own',
            'sessions.revoke-own',
        ],
        RoleName::Service->value => [
            'users.list',
            'users.list-all',
            'users.view-email',
            'roles.list',
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
     * Idempotent by design, so this is safe to call from a dated reseed migration as well as from
     * `db:seed`: the upserts converge on the declared matrix whether or not the rows already
     * exist. That is what lets a repair migration reach databases that migrated before a
     * permission was added, which the deploy path cannot do because `composer setup` runs
     * `migrate --force` without `--seed`.
     *
     * The writes are single atomic `upsert` statements rather than `findOrCreate`'s
     * SELECT-then-INSERT, because the read-then-write shape loses a race: two seeders sharing
     * one database both miss the read and both write, and the loser's plain INSERT dies on the
     * unique key (`1062 Duplicate entry`). An upsert turns that losing write into an UPDATE of
     * the winner's row, so an overlapping run converges instead of erroring.
     *
     * The permission cache is flushed after the upserts and before the sync. Spatie caches the
     * resolved permission collection, and a stale collection would not contain rows the upsert
     * just created - resolving the grant names through it would then fail the sync. The flush
     * cannot go before the upserts for the same reason it never could: anything reading
     * permissions later in the process would still see the pre-reseed matrix until it runs.
     *
     * @return void
     */
    public function run(): void
    {
        /*
         * Every task that applies the reseed migration runs this seeder, and `syncPermissions`
         * detaches a role's grants before re-attaching them, so overlapping runs can duplicate a
         * pivot key or leave a role holding none. This is the primitive `migrate --isolated` uses,
         * with the same meaning: a run that cannot take the lock does not execute at all. The
         * duration bounds a lock whose holder died before it could release.
         */
        $lock = Cache::lock(self::LOCK_NAME, self::LOCK_TTL);

        if (! (bool) $lock->get()) {
            return;
        }

        try {
            $this->writeMatrix();
        } finally {
            $lock->release();
        }
    }

    /**
     * Write the declared matrix: upsert both tables, flush the cache, then grant.
     *
     * Runs under the seeder's lock, so the detach-then-attach inside `syncPermissions` cannot
     * interleave with another run's.
     *
     * @return void
     */
    private function writeMatrix(): void
    {
        $now = now();

        /*
         * Read the guard from the same derivation `Role::findByName` below resolves through: a
         * literal here seeds rows this run cannot then find whenever the configured default guard
         * is anything else, and the reseed dies on the sync instead of converging.
         */
        $guardName = Guard::getDefaultName(Permission::class);

        Permission::upsert(
            array_map(
                static fn (string $permission): array => [
                    'name' => $permission,
                    'guard_name' => $guardName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                self::PERMISSIONS,
            ),
            ['name', 'guard_name'],
            ['updated_at'],
        );

        Role::upsert(
            array_map(
                static fn (string $roleName): array => [
                    'name' => $roleName,
                    'guard_name' => $guardName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                array_keys(self::ROLE_PERMISSIONS),
            ),
            ['name', 'guard_name'],
            ['updated_at'],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::findByName($roleName)->syncPermissions($permissions);
        }
    }
}
