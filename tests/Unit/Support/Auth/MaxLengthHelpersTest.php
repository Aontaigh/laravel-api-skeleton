<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\EmailMaxLength;
use App\Support\Auth\PasswordMaxLength;
use App\Support\Auth\PasswordResetTokenMaxLength;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the config-driven input bound helpers.
 */
#[CoversClass(EmailMaxLength::class)]
#[CoversClass(PasswordMaxLength::class)]
#[CoversClass(PasswordResetTokenMaxLength::class)]
final class MaxLengthHelpersTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Read each bound from its config key.
     */
    #[Test]
    public function it_reads_each_bound_from_config(): void
    {
        // Arrange

        config([
            'api.email_max_length' => 200,
            'api.password_max_length' => 100,
            'api.password_reset_token_max_length' => 64,
        ]);

        // Act + Assert

        $this->assertSame(200, EmailMaxLength::value());
        $this->assertSame(100, PasswordMaxLength::value());
        $this->assertSame(64, PasswordResetTokenMaxLength::value());
    }

    /**
     * Build `max:{n}` rule strings from the configured bounds.
     */
    #[Test]
    public function it_builds_max_rules_from_the_configured_bounds(): void
    {
        // Arrange

        config([
            'api.email_max_length' => 200,
            'api.password_max_length' => 100,
            'api.password_reset_token_max_length' => 64,
        ]);

        // Act + Assert

        $this->assertSame('max:200', EmailMaxLength::rule());
        $this->assertSame('max:100', PasswordMaxLength::rule());
        $this->assertSame('max:64', PasswordResetTokenMaxLength::rule());
    }

    /**
     * Surface the Title Case copy on `max` rule failures.
     */
    #[Test]
    public function it_surfaces_title_case_copy_on_max_failures(): void
    {
        // Arrange

        $validator = validator(
            ['password' => str_repeat('x', 101)],
            ['password' => ['max:100']],
            ['password.max' => PasswordMaxLength::MESSAGE],
        );

        // Act + Assert

        $this->assertSame('Password Is Too Long', $validator->errors()->first('password'));
    }
}
