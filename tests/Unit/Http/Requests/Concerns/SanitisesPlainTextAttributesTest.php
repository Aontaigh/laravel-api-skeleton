<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\SanitisesPlainTextAttributes;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the `SanitisesPlainTextAttributes` trait.
 */
#[CoversTrait(SanitisesPlainTextAttributes::class)]
final class SanitisesPlainTextAttributesTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Strip HTML tags from declared plain text attributes.
     */
    #[Test]
    public function it_strips_html_tags_from_declared_plain_text_attributes(): void
    {
        // Arrange

        $request = PlainTextHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => '<script>alert(1)</script>Token']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $request->prepareForValidation();

        // Assert

        $this->assertSame('alert(1)Token', $request->input('name'));
    }

    /**
     * Collapse repeated whitespace into single spaces.
     */
    #[Test]
    public function it_collapses_repeated_whitespace_into_single_spaces(): void
    {
        // Arrange

        $request = PlainTextHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => "My\n\t Token"]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $request->prepareForValidation();

        // Assert

        $this->assertSame('My Token', $request->input('name'));
    }

    /**
     * Trim leading and trailing whitespace.
     */
    #[Test]
    public function it_trims_leading_and_trailing_whitespace(): void
    {
        // Arrange

        $request = PlainTextHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => '  Padded  ']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $request->prepareForValidation();

        // Assert

        $this->assertSame('Padded', $request->input('name'));
    }

    /**
     * Skip attributes not declared in plain text attribute keys.
     */
    #[Test]
    public function it_skips_attributes_not_declared_in_plain_text_attribute_keys(): void
    {
        // Arrange

        $request = PlainTextHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['description' => '<b>bold</b>', 'name' => 'Token']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $request->prepareForValidation();

        // Assert

        $this->assertSame('<b>bold</b>', $request->input('description'));
        $this->assertSame('Token', $request->input('name'));
    }

    /**
     * Leave non-string attributes untouched.
     */
    #[Test]
    public function it_leaves_non_string_attributes_untouched(): void
    {
        // Arrange

        $request = PlainTextHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => ['array', 'value']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $request->prepareForValidation();

        // Assert

        $this->assertSame(['array', 'value'], $request->input('name'));
    }
}

/**
 * Minimal harness exposing plain-text sanitisation for unit tests.
 */
final class PlainTextHarness extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------​
    | Traits
    |--------------------------------------------------------------------------​
    */

    use SanitisesPlainTextAttributes;

    /*
    |--------------------------------------------------------------------------​
    | Public
    |--------------------------------------------------------------------------​
    */

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the validation rules
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
        ];
    }

    /**
     * The attribute keys to sanitise as plain text.
     *
     * @return list<string> the attribute names to sanitise
     */
    protected function plainTextAttributeKeys(): array
    {
        return ['name'];
    }

    /**
     * Expose the protected sanitisation hook for testing.
     */
    public function prepareForValidation(): void
    {
        $this->sanitisePlainTextAttributes();
    }
}
