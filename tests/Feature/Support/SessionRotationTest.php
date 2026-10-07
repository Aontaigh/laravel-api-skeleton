<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the session store behaviour the remember-me flow relies on.
 */
#[CoversNothing]
final class SessionRotationTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Regenerate rotates the session ID, invalidating the prior (fixated) ID.
     *
     * This is the primitive `LoginController` and `RememberLoginController` call
     * at the privilege boundary - proving it directly documents the defence the
     * controllers rely on.
     */
    #[Test]
    public function it_rotates_the_session_id_on_regenerate(): void
    {
        // Arrange

        $this->startSession();

        /** @var string $beforeId */
        $beforeId = session()->getId();

        // Act

        session()->regenerate();

        // Assert

        $this->assertNotSame($beforeId, session()->getId());
    }
}
