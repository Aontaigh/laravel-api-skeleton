<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\RoleName;
use App\Policies\AuthAuditLogPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the dated reseed migrations that repair an upgraded database.
 *
 * The deploy path runs `artisan migrate --force` without `--seed`, so a permission added to
 * `RolesAndPermissionsSeeder` never reaches a database that migrated earlier. These tests prove the
 * repair migration closes that gap rather than taking the seeder's word for it.
 */
#[CoversNothing]
final class RolesAndPermissionsMigrationTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Restore the Auth Audit Log read permission when the seeder re-runs.
     *
     * Simulates the upgrade path directly: delete the row a deployed database would be missing,
     * run the migration, and assert the permission is back on Admin and absent from Service. The
     * deletion is the whole point - without it the assertion would pass on a fresh database and
     * prove nothing about an upgrade.
     */
    #[Test]
    public function it_restores_the_audit_log_read_permission_when_the_seeder_reruns(): void
    {
        // Arrange

        Permission::query()->where('name', AuthAuditLogPolicy::LIST_PERMISSION)->delete();

        $this->assertFalse(
            Permission::query()->where('name', AuthAuditLogPolicy::LIST_PERMISSION)->exists(),
            'The permission must start absent, otherwise the repair proves nothing.',
        );

        // Act

        $this->runReseedMigration();

        // Assert

        $this->assertDatabaseHas('permissions', [
            'name' => AuthAuditLogPolicy::LIST_PERMISSION,
            'guard_name' => 'web',
        ]);

        $this->assertTrue(
            Role::findByName(RoleName::Admin->value)->hasPermissionTo(AuthAuditLogPolicy::LIST_PERMISSION),
            'Admin should hold the permission after the reseed.',
        );

        $this->assertFalse(
            Role::findByName(RoleName::Service->value)->hasPermissionTo(AuthAuditLogPolicy::LIST_PERMISSION),
            'Service must not hold the permission after the reseed.',
        );
    }

    /**
     * The reseed is safe to run on a database that already holds the matrix.
     *
     * A repair migration runs on fresh databases too, where the seeder has already run, so a
     * second pass must converge rather than duplicate or fail.
     */
    #[Test]
    public function it_is_idempotent_when_the_matrix_is_already_present(): void
    {
        // Arrange

        $before = DB::table('permissions')->count();
        $grantsBefore = DB::table('role_has_permissions')->count();

        // Act

        $this->runReseedMigration();
        $this->runReseedMigration();

        // Assert

        $this->assertSame(
            $before,
            DB::table('permissions')->count(),
            'Re-running the seeder must not add duplicate permissions.',
        );
        $this->assertSame(
            $grantsBefore,
            DB::table('role_has_permissions')->count(),
            'Re-running the seeder must not duplicate role grants.',
        );
    }

    /**
     * The declared matrix reaches an upgraded database exactly as a fresh one gets it.
     *
     * This is the regression the whole pattern exists to prevent: the seeder and the repair
     * migration must agree, or the bug returns the next time a permission is added.
     */
    #[Test]
    public function it_leaves_every_declared_permission_and_grant_in_place(): void
    {
        // Arrange

        // Act

        $this->runReseedMigration();

        // Assert

        $reflection = new ReflectionClass(RolesAndPermissionsSeeder::class);

        /** @var list<string> $declared */
        $declared = $reflection->getConstant('PERMISSIONS');

        foreach ($declared as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        /** @var array<string, list<string>> $rolePermissions */
        $rolePermissions = $reflection->getConstant('ROLE_PERMISSIONS');

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findByName($roleName);

            foreach ($permissions as $permission) {
                $this->assertTrue(
                    $role->hasPermissionTo($permission),
                    sprintf('Role [%s] should hold [%s] after the reseed.', $roleName, $permission),
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Run the dated reseed migration in place.
     *
     * `require` on a migration file yields the anonymous class, which has no declared return type
     * for static analysis to read, so the result is asserted as a `Migration` before `up()` is
     * called. Loading the file directly rather than through Artisan is deliberate: the point is to
     * prove the migration repairs a database, which is exactly what `migrate` would not do on a
     * database that has already run it.
     *
     * @return void
     */
    private function runReseedMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_10_07_000001_reseed_roles_and_permissions.php',
        );

        $this->assertInstanceOf(Migration::class, $migration);

        /*
         * Called through `call_user_func` rather than `$migration->up()` on purpose. The base
         * `Migration` class declares neither `up()` nor `down()` - that is why every migration file
         * returns an anonymous subclass rather than a class with those methods - so a direct call is
         * a method static analysis cannot resolve, even though it is valid at runtime.
         */
        $this->assertTrue(
            method_exists($migration, 'up'),
            'The migration file must return an object exposing up().',
        );

        call_user_func([$migration, 'up']);
    }
}
