<?php

declare(strict_types=1);

namespace Tests;

/**
 * Base class for unit tests that must not touch the database.
 *
 * Feature tests use {@see TestCase} with {@see Illuminate\Foundation\Testing\RefreshDatabase}.
 * Unit tests extend this class so any accidental query fails the test at teardown.
 */
abstract class UnitTestCase extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Fail the test when any database query runs during a unit test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->expectsDatabaseQueryCount(0);
    }
}
