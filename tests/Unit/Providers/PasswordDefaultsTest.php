<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the application-wide password policy wired by the service provider.
 */
#[CoversClass(AppServiceProvider::class)]
final class PasswordDefaultsTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the strict default policy (length, case, numbers, breach check).
     */
    #[Test]
    public function it_applies_the_strict_default_password_policy(): void
    {
        // Arrange

        /** @var Password $default */
        $default = Password::defaults();

        // Act

        $weakOrBreached = [
            $this->passes($default, 'short'),
            $this->passes($default, 'secretpass12'),
            $this->passes($default, 'SecretPassword'),
            $this->passes($default, 'Password123'),
        ];

        $strong = $this->passes($default, $this->uniqueStrongPassword());

        // Assert

        /*
         * Every weak or breached candidate is rejected; only the strong
         * unique password passes.
         */

        $this->assertSame([false, false, false, false], $weakOrBreached);
        $this->assertTrue($strong);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Run the validator with the given rule against a candidate password.
     *
     * @param  Password $rule      the rule under test
     * @param  string   $candidate the password candidate
     * @return bool     true when the candidate passes
     */
    private function passes(Password $rule, string $candidate): bool
    {
        return validator(['password' => $candidate], ['password' => $rule])->passes();
    }

    /**
     * Build a password that satisfies the policy without tripping the breach list.
     *
     * @return string a strong, unique password
     */
    private function uniqueStrongPassword(): string
    {
        return 'Str0ng-'.bin2hex(random_bytes(6));
    }
}
