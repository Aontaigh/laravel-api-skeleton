<?php

declare(strict_types=1);

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-seeds the roles and permissions matrix on databases that migrated before this release.
 *
 * A migration does not run twice, and the deploy path does not seed: `composer setup` runs
 * `artisan migrate --force` with no `--seed`, so a permission added to
 * `RolesAndPermissionsSeeder::PERMISSIONS` reaches a fresh database and CI but never an existing
 * deployed one. The result is a silent 403 for Admin on the Auth Audit Log surface, with no failing
 * test anywhere to say why.
 *
 * Re-running the seeder closes that gap because it is already idempotent: `findOrCreate` and
 * `syncPermissions` converge on the declared matrix whether or not the rows already exist, so this
 * migration is safe on a fresh database and on one that has been running for months.
 *
 * Later permission changes need their own dated migration in this same shape. Editing an existing
 * one is not enough, and deleting these as duplicates is not a cleanup: each exists precisely
 * because the databases it has already run against are the ones that need it.
 */
return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op. Removing the matrix would cascade to `model_has_roles` and strip
     * every account of its access, and a `down()` that destroys data is worse than one that does
     * nothing on an idempotent repair like this.
     *
     * @return void
     */
    public function down(): void {}
};
