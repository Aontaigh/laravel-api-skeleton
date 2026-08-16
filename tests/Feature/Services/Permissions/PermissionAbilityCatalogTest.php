<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Permissions;

use App\Services\Permissions\PermissionAbilityCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for PermissionAbilityCatalog against the seeded database.
 */
#[CoversClass(PermissionAbilityCatalog::class)]
final class PermissionAbilityCatalogTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed the permission catalog every test reads from.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Read every seeded permission name from the database.
     */
    #[Test]
    public function it_reads_every_seeded_permission_name_from_the_database(): void
    {
        // Act

        $names = app(PermissionAbilityCatalog::class)->allNames();

        // Assert

        $this->assertContains('users.list', $names);
        $this->assertContains('tokens.list-own', $names);
        $this->assertContains('users.delete', $names);
        $this->assertCount(19, $names);
    }

    /**
     * Normalize registered permission names from the database catalog.
     */
    #[Test]
    public function it_normalizes_registered_permission_names_from_the_database_catalog(): void
    {
        // Act

        $abilities = app(PermissionAbilityCatalog::class)->normalizeTokenAbilities([
            'tokens.list-own',
            'tokens.revoke-own',
        ]);

        // Assert

        $this->assertSame(['tokens.list-own', 'tokens.revoke-own'], $abilities);
    }
}
