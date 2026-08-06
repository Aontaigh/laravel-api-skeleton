<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Permissions;

use App\Exceptions\InvalidTokenAbilitiesException;
use App\Services\Permissions\PermissionAbilityCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for PermissionAbilityCatalog.
 *
 * Uses an in-memory permission list — no database, no Spatie queries.
 */
#[CoversClass(PermissionAbilityCatalog::class)]
final class PermissionAbilityCatalogTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    private const CATALOG_NAMES = [
        'roles.list',
        'tokens.list-own',
        'tokens.revoke-own',
        'users.list',
    ];

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Build a catalog backed by a fixed in-memory permission list.
     */
    private function catalog(): PermissionAbilityCatalog
    {
        return new PermissionAbilityCatalog(
            fn (): array => self::CATALOG_NAMES,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * List every permission name from the resolver.
     */
    #[Test]
    public function it_lists_every_permission_name_from_the_resolver(): void
    {
        // Act

        $names = $this->catalog()->allNames();

        // Assert

        $this->assertSame(self::CATALOG_NAMES, $names);
    }

    /**
     * Report known and unknown permission names.
     */
    #[Test]
    public function it_reports_known_and_unknown_permission_names(): void
    {
        // Arrange

        $catalog = $this->catalog();

        // Act & Assert

        $this->assertTrue($catalog->exists('roles.list'));
        $this->assertFalse($catalog->exists('not-a-permission'));
    }

    /**
     * Treat a single wildcard as unrestricted.
     */
    #[Test]
    public function it_treats_a_single_wildcard_as_unrestricted(): void
    {
        // Arrange

        $catalog = $this->catalog();

        // Act & Assert

        $this->assertTrue($catalog->isUnrestricted(['*']));
        $this->assertFalse($catalog->isUnrestricted(['tokens.list-own']));
    }

    /**
     * Normalize the wildcard ability.
     */
    #[Test]
    public function it_normalizes_the_wildcard_ability(): void
    {
        // Act

        $abilities = $this->catalog()->normalizeTokenAbilities(['*']);

        // Assert

        $this->assertSame(['*'], $abilities);
    }

    /**
     * Normalize registered permission names.
     */
    #[Test]
    public function it_normalizes_registered_permission_names(): void
    {
        // Act

        $abilities = $this->catalog()->normalizeTokenAbilities([
            'tokens.list-own',
            'tokens.revoke-own',
        ]);

        // Assert

        $this->assertSame(['tokens.list-own', 'tokens.revoke-own'], $abilities);
    }

    /**
     * Deduplicate repeated permission names.
     */
    #[Test]
    public function it_deduplicates_repeated_permission_names(): void
    {
        // Act

        $abilities = $this->catalog()->normalizeTokenAbilities([
            'tokens.list-own',
            'tokens.list-own',
        ]);

        // Assert

        $this->assertSame(['tokens.list-own'], $abilities);
    }

    /**
     * Reject unknown ability names.
     */
    #[Test]
    public function it_rejects_unknown_ability_names(): void
    {
        // Arrange

        $catalog = $this->catalog();

        // Act & Assert

        $this->expectException(InvalidTokenAbilitiesException::class);

        try {
            $catalog->normalizeTokenAbilities(['read', 'not-real']);
        } catch (InvalidTokenAbilitiesException $exception) {
            $this->assertSame(['read', 'not-real'], $exception->invalidAbilities());

            throw $exception;
        }
    }

    /**
     * Reject an empty ability list.
     */
    #[Test]
    public function it_rejects_an_empty_ability_list(): void
    {
        // Arrange

        $catalog = $this->catalog();

        // Act & Assert

        $this->expectException(InvalidTokenAbilitiesException::class);

        try {
            $catalog->normalizeTokenAbilities([]);
        } catch (InvalidTokenAbilitiesException $exception) {
            $this->assertSame(['(empty)'], $exception->invalidAbilities());

            throw $exception;
        }
    }

    /**
     * Reject a wildcard mixed with explicit permissions.
     */
    #[Test]
    public function it_rejects_a_wildcard_mixed_with_explicit_permissions(): void
    {
        // Arrange

        $catalog = $this->catalog();

        // Act & Assert

        $this->expectException(InvalidTokenAbilitiesException::class);

        $catalog->normalizeTokenAbilities(['*', 'tokens.list-own']);
    }
}
