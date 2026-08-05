<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Roles;

use App\DataTransferObjects\Roles\RoleFilters;
use App\Queries\Roles\RoleFilterQuery;
use App\Queries\Roles\RoleQueryConstraints;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unit tests for RoleFilterQuery guard scoping and filters.
 */
#[CoversClass(RoleFilterQuery::class)]
final class RoleFilterQueryTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_scopes_results_to_the_default_guard(): void
    {
        // Arrange

        /** @var Builder<Role> $query */
        $query = Role::query();

        /** @var RoleFilters $filters */
        $filters = new RoleFilters(search: null);

        // Act

        (new RoleFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame(RoleQueryConstraints::TABLE.'.guard_name', $query->getQuery()->wheres[0]['column']);
        $this->assertSame([RoleQueryConstraints::GUARD_NAME], $query->getBindings());
    }

    #[Test]
    #[DataProvider('searchTermProvider')]
    public function it_escapes_like_wildcards_in_the_search_term(string $term, string $expectedPattern): void
    {
        // Arrange

        /** @var Builder<Role> $query */
        $query = Role::query();

        /** @var RoleFilters $filters */
        $filters = new RoleFilters(search: $term);

        // Act

        (new RoleFilterQuery)->apply($query, $filters);

        // Assert

        $this->assertSame([RoleQueryConstraints::GUARD_NAME, $expectedPattern], $query->getBindings());
        $this->assertStringContainsString(
            'ESCAPE',
            (string) ($query->getQuery()->wheres[1]['sql'] ?? ''),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Search terms mapped to the `LIKE` pattern they must produce.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [term, expectedPattern]
     */
    public static function searchTermProvider(): array
    {
        return [
            'plain term is wrapped only' => ['Admin', '%Admin%'],
            'match-all wildcard is escaped' => ['%', '%\%%'],
            'single-character wildcard is escaped' => ['_', '%\_%'],
            'escape character is doubled' => ['\\', '%\\\\%'],
            'mixed wildcards are escaped' => ['Ad%min', '%Ad\%min%'],
        ];
    }
}
