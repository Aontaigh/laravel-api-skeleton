<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\Concerns\Tokens\ValidatesTokenPayload;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Http\Requests\Concerns\TokenPayloadHarness;
use Tests\TestCase;

/**
 * Unit tests for the `ValidatesTokenPayload` trait.
 */
#[CoversTrait(ValidatesTokenPayload::class)]
final class ValidatesTokenPayloadTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the expected Token payload rules.
     */
    #[Test]
    public function it_returns_the_expected_token_payload_rules(): void
    {
        // Arrange

        $request = TokenPayloadHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => 'Token']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $rules = $request->rules();

        // Assert

        $this->assertSame(['required', 'string', 'max:255'], $rules['name']);
        $this->assertSame(['sometimes', 'array'], $rules['abilities']);
        $this->assertSame(['string'], $rules['abilities.*']);
    }

    /**
     * Default Token abilities to wildcard when omitted.
     */
    #[Test]
    public function it_defaults_token_abilities_to_wildcard_when_omitted(): void
    {
        // Arrange

        $request = TokenPayloadHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => 'Token']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        $validator = validator($request->request->all(), $request->rules());
        $request->setValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame(['*'], $request->tokenAbilities());
    }

    /**
     * Return explicit abilities when provided.
     */
    #[Test]
    public function it_returns_explicit_abilities_when_provided(): void
    {
        // Arrange

        $request = TokenPayloadHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => 'Token', 'abilities' => ['read', 'write']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        $validator = validator($request->request->all(), $request->rules());
        $request->setValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame(['read', 'write'], $request->tokenAbilities());
    }

    /**
     * Sanitise the name attribute as plain text.
     */
    #[Test]
    public function it_sanitises_the_name_attribute_as_plain_text(): void
    {
        // Arrange

        $request = TokenPayloadHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => '<b>My</b> Token']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $request->prepareForValidation();

        // Assert

        $this->assertSame('My Token', $request->input('name'));
    }

    /**
     * Declare name as the only plain text attribute key.
     */
    #[Test]
    public function it_declares_name_as_the_only_plain_text_attribute_key(): void
    {
        // Arrange

        $request = TokenPayloadHarness::createFrom(
            Request::create('/api/tokens', 'POST', ['name' => 'Token']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('POST', '/api/tokens', []));

        // Act

        $keys = $request->exposedPlainTextAttributeKeys();

        // Assert

        $this->assertSame(['name'], $keys);
    }
}
