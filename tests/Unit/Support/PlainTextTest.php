<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PlainText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for PlainText sanitisation.
 */
#[CoversClass(PlainText::class)]
final class PlainTextTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    #[DataProvider('hostileNameProvider')]
    public function it_strips_markup_from_plain_text_values(string $input, string $expected): void
    {
        // Act

        $sanitised = PlainText::sanitize($input);

        // Assert

        $this->assertSame($expected, $sanitised);
        $this->assertStringNotContainsString('<', $sanitised);
        $this->assertStringNotContainsString('>', $sanitised);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Hostile display names mapped to the sanitised plain text.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [input, expected]
     */
    public static function hostileNameProvider(): array
    {
        return [
            'script tag' => ['<script>alert(1)</script>', 'alert(1)'],
            'image onerror' => ['<img onerror=alert(1) src=x>', ''],
            'nested markup' => ['<b>Bold <i>Name</i></b>', 'Bold Name'],
            'plain text unchanged' => ['Acme User', 'Acme User'],
        ];
    }
}
