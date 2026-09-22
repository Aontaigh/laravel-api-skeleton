<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\PasswordByteLength;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the byte-length cap on hashed password fields.
 */
#[CoversClass(PasswordByteLength::class)]
final class PasswordByteLengthTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Build a fail Closure matching the rule's signature, recording the
     * message it is called with.
     *
     * @param  string|null                                                          $into recorded message, set when the rule fails
     * @return Closure(string): \Illuminate\Translation\PotentiallyTranslatedString
     */
    private function failRecorder(?string &$into): Closure
    {
        $into = null;

        return function (string $failure) use (&$into): \Illuminate\Translation\PotentiallyTranslatedString {
            $into = $failure;

            return new \Illuminate\Translation\PotentiallyTranslatedString(
                $failure,
                app('translator'),
            );
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Accept a password at or under the 72-byte hash input limit.
     */
    #[Test]
    public function it_accepts_a_password_at_the_byte_limit(): void
    {
        // Arrange

        $rule = new PasswordByteLength;
        $atLimit = str_repeat('a', PasswordByteLength::MAX_BYTES);
        $message = null;

        // Act

        $rule->validate('password', $atLimit, $this->failRecorder($message));

        // Assert

        self::assertNull($message);
    }

    /**
     * Reject a password whose byte length exceeds the hash input limit, even
     * when its character count is inside the character-level cap.
     */
    #[Test]
    public function it_rejects_a_multibyte_password_over_72_bytes(): void
    {
        // Arrange

        $rule = new PasswordByteLength;

        /*
         * 36 two-byte characters: 36 characters (inside `max:255`), 72 bytes at
         * the limit - one more two-byte character crosses the byte limit while
         * staying far under the character cap.
         */
        $multibyte = str_repeat('é', 36);

        $overBytes = $multibyte.'é';

        $message = null;

        // Act

        $rule->validate('password', $overBytes, $this->failRecorder($message));

        // Assert

        self::assertSame('Password Must Not Exceed 72 Bytes', $message);
    }

    /**
     * Ignore a non-string value; the `string` rule owns that rejection.
     */
    #[Test]
    public function it_ignores_a_non_string_value(): void
    {
        // Arrange

        $rule = new PasswordByteLength;
        $message = null;

        // Act

        $rule->validate('password', ['array'], $this->failRecorder($message));

        // Assert

        self::assertNull($message);
    }

    /**
     * Keep the byte limit pinned to what the hash driver actually truncates at.
     */
    #[Test]
    public function it_pins_the_bcrypt_input_limit(): void
    {
        // Arrange

        /*
         * The limit is a class constant, so this guard is for drift readers,
         * not the analyser: the configured character cap must always exceed
         * the byte cap, or the byte rule could never fire.
         */
        $documented = config()->integer('api.password_max_length', 255);
        $limit = PasswordByteLength::MAX_BYTES;

        // Assert

        self::assertLessThan($documented, $limit);
    }
}
