<?php

declare(strict_types=1);

namespace Tests\Unit\Queries;

use App\Models\User;
use App\Queries\ListFieldsQuery;
use App\Queries\Tokens\TokenQueryConstraints;
use App\Queries\Users\UserQueryConstraints;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the shared ListFieldsQuery.
 *
 * Composition is asserted against the builder's own column list, so these run
 * with no database.
 */
#[CoversClass(ListFieldsQuery::class)]
final class ListFieldsQueryTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_selects_only_whitelisted_sparse_columns_plus_required_columns(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        // Act

        (new ListFieldsQuery)->apply(
            query: $query,
            requestedFields: ['name'],
            allowedFields: UserQueryConstraints::ALLOWED_FIELDS,
            table: 'users',
            requiredColumns: ['id'],
        );

        // Assert

        $this->assertSame(['users.id', 'users.name'], $query->getQuery()->columns);
    }

    #[Test]
    public function it_drops_requested_columns_outside_the_allow_list(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        // Act

        (new ListFieldsQuery)->apply(
            query: $query,
            requestedFields: ['name', 'password', 'team_id'],
            allowedFields: UserQueryConstraints::ALLOWED_FIELDS,
            table: 'users',
            requiredColumns: ['id'],
        );

        // Assert

        $this->assertSame(['users.id', 'users.name'], $query->getQuery()->columns);
    }

    #[Test]
    public function it_selects_required_columns_that_are_not_client_requestable(): void
    {
        /*
         * `team_id` backs the `team` include but is deliberately absent from
         * ALLOWED_FIELDS, so it must arrive via the required-column path.
         */

        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        // Act

        (new ListFieldsQuery)->apply(
            query: $query,
            requestedFields: ['name'],
            allowedFields: UserQueryConstraints::ALLOWED_FIELDS,
            table: 'users',
            requiredColumns: UserQueryConstraints::requiredSelectColumns(['team']),
        );

        // Assert

        $this->assertSame(
            ['users.id', 'users.team_id', 'users.name'],
            $query->getQuery()->columns,
        );
    }

    #[Test]
    public function it_selects_the_default_fieldset_when_no_sparse_fieldset_is_requested(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        // Act

        (new ListFieldsQuery)->apply(
            query: $query,
            requestedFields: null,
            allowedFields: UserQueryConstraints::ALLOWED_FIELDS,
            table: 'users',
            requiredColumns: ['id'],
        );

        // Assert

        $this->assertSame(
            ['users.id', 'users.name', 'users.created_at'],
            $query->getQuery()->columns,
        );
    }

    #[Test]
    public function it_projects_token_rows_without_the_hash_column_by_default(): void
    {
        // Arrange

        /** @var Builder<PersonalAccessToken> $query */
        $query = PersonalAccessToken::query();

        // Act

        (new ListFieldsQuery)->apply(
            query: $query,
            requestedFields: null,
            allowedFields: TokenQueryConstraints::ALLOWED_FIELDS,
            table: TokenQueryConstraints::TABLE,
            requiredColumns: TokenQueryConstraints::requiredSelectColumns(),
        );

        // Assert

        $this->assertNotContains(
            'personal_access_tokens.token',
            $query->getQuery()->columns ?? [],
        );
    }

    #[Test]
    public function it_selects_only_required_columns_when_the_fieldset_is_empty(): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        // Act

        (new ListFieldsQuery)->apply(
            query: $query,
            requestedFields: [],
            allowedFields: UserQueryConstraints::ALLOWED_FIELDS,
            table: 'users',
            requiredColumns: ['id'],
        );

        // Assert

        $this->assertSame(['users.id'], $query->getQuery()->columns);
    }

    #[Test]
    #[DataProvider('unsafeIdentifierProvider')]
    public function it_rejects_identifiers_that_could_carry_sql(string $table, string $requiredColumn): void
    {
        // Arrange

        /** @var Builder<User> $query */
        $query = User::query();

        // Act + Assert

        $this->expectException(InvalidArgumentException::class);

        (new ListFieldsQuery)->apply(
            query: $query,
            requestedFields: ['name'],
            allowedFields: UserQueryConstraints::ALLOWED_FIELDS,
            table: $table,
            requiredColumns: [$requiredColumn],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Table and required-column pairs that must never reach `select()`.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [table, requiredColumn]
     */
    public static function unsafeIdentifierProvider(): array
    {
        return [
            'statement terminator in column' => ['users', 'id; drop table users'],
            'subquery in column' => ['users', 'id) from users where (true'],
            'comment marker in column' => ['users', 'id--'],
            'wildcard column' => ['users', '*'],
            'empty column' => ['users', ''],
            'injection in table' => ['users; drop table users', 'id'],
        ];
    }
}
