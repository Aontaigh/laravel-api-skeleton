<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\QualifiedColumn;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for QualifiedColumn.
 *
 * Pure string logic, so these run without a database.
 */
#[CoversClass(QualifiedColumn::class)]
final class QualifiedColumnTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Join a bare table and column with a dot.
     */
    #[Test]
    public function it_joins_a_bare_table_and_column_with_a_dot(): void
    {
        // Act

        $qualified = QualifiedColumn::make('users', 'name');

        // Assert

        $this->assertSame('users.name', $qualified);
    }

    /**
     * Reject identifiers that could carry SQL.
     */
    #[Test]
    /**
     * Reject identifiers that could carry SQL.
     */
    #[DataProvider('unsafeIdentifierProvider')]
    public function it_rejects_identifiers_that_could_carry_sql(string $table, string $column): void
    {
        // Act + Assert

        $this->expectException(InvalidArgumentException::class);

        QualifiedColumn::make($table, $column);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Table and column pairs that must never reach `select()` or `orderBy()`.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [table, column]
     */
    public static function unsafeIdentifierProvider(): array
    {
        return [
            'statement terminator in column' => ['users', 'id; drop table users'],
            'subquery in column' => ['users', 'id) from users where (true'],
            'comment marker in column' => ['users', 'id--'],
            'wildcard column' => ['users', '*'],
            'empty column' => ['users', ''],
            'space in column' => ['users', 'id name'],
            'injection in table' => ['users; drop table users', 'id'],
            'empty table' => ['', 'id'],
            'column starting with a digit' => ['users', '1id'],
        ];
    }
}
