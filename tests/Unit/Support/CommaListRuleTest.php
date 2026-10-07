<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CommaListRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for CommaListRule.
 *
 * The rule is the only place the list cap and the per-value allow-list are enforced, so these
 * cover the two shapes a caller can get wrong: too many values, and a value outside the list.
 */
#[CoversClass(CommaListRule::class)]
final class CommaListRuleTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Pass a list whose values are all allowed.
     */
    #[Test]
    public function it_passes_a_list_of_allowed_values(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['status' => 'active,suspended']],
            ['filter.status' => [CommaListRule::in(3, ['active', 'suspended', 'deleted'])]],
        );

        // Assert

        $this->assertFalse($validator->fails());
    }

    /**
     * Reject a list containing a value outside the allow-list, naming the offender.
     */
    #[Test]
    public function it_rejects_a_value_outside_the_allow_list(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['status' => 'active,nonsense']],
            ['filter.status' => [CommaListRule::in(3, ['active', 'suspended', 'deleted'])]],
        );

        // Assert

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('nonsense', $validator->errors()->first('filter.status'));
    }

    /**
     * Reject a list past the cap without validating each part, so the message names the cap.
     */
    #[Test]
    public function it_rejects_a_list_past_the_cap(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['role' => 'Admin,Manager,User,Service,Extra']],
            ['filter.role' => [CommaListRule::in(4, ['Admin', 'Manager', 'User', 'Service'])]],
        );

        // Assert

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'At Most 4',
            $validator->errors()->first('filter.role'),
        );
    }

    /**
     * Ignore a non-string value, leaving the type rule to reject it.
     */
    #[Test]
    public function it_ignores_a_non_string_value(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['role' => ['Admin', 'User']]],
            ['filter.role' => [CommaListRule::in(4, ['Admin'])]],
        );

        // Assert

        $this->assertFalse($validator->fails());
    }

    /**
     * Pass when only the cap is given and no inner rule is configured.
     */
    #[Test]
    public function it_passes_a_list_when_no_inner_rule_is_configured(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['user_id' => '1,2,3']],
            ['filter.user_id' => [new CommaListRule(5)]],
        );

        // Assert

        $this->assertFalse($validator->fails());
    }

    /**
     * Accept a list sitting exactly on the cap.
     */
    #[Test]
    public function it_accepts_a_list_exactly_at_the_cap(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['user_id' => '1,2,3']],
            ['filter.user_id' => [new CommaListRule(3)]],
        );

        // Assert

        $this->assertFalse($validator->fails());
    }

    /**
     * Count the raw parts before de-duplication, so repeats cannot smuggle past a cap.
     */
    #[Test]
    public function it_counts_parts_before_deduplication(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['user_id' => '1,1,1']],
            ['filter.user_id' => [new CommaListRule(2)]],
        );

        // Assert

        $this->assertTrue($validator->fails());
    }

    /**
     * Reject a disallowed value when the attribute key itself has no dot.
     */
    #[Test]
    public function it_rejects_a_disallowed_value_under_a_flat_key(): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['status' => 'active,nonsense'],
            ['status' => [CommaListRule::in(3, ['active', 'suspended'])]],
        );

        // Assert

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('nonsense', $validator->errors()->first('status'));
    }

    /**
     * Accept every blank form, since a blank filter means "unfiltered".
     */
    #[Test]
    #[DataProvider('blankValuesProvider')]
    public function it_accepts_a_blank_value(?string $raw): void
    {
        // Arrange

        // Act

        $validator = Validator::make(
            ['filter' => ['status' => $raw]],
            ['filter.status' => [CommaListRule::in(3, ['active'])]],
        );

        // Assert

        $this->assertFalse($validator->fails());
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Values that mean "no filter supplied".
     *
     * @return array<string, array{0: string|null}>
     */
    public static function blankValuesProvider(): array
    {
        return [
            'absent' => [null],
            'empty string' => [''],
            'commas only' => [',,'],
        ];
    }
}
