<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ParsesIncludeQueryParam;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the `include` query-param parsing and allow-list validation.
 */
#[CoversTrait(ParsesIncludeQueryParam::class)]
final class ParsesIncludeQueryParamTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Parse and filter includes by the allow list.
     */
    #[Test]
    public function it_parses_and_filters_includes_by_the_allow_list(): void
    {
        // Arrange

        $request = IncludeQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['include' => 'posts,team']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame(['posts', 'team'], $request->includes());
    }

    /**
     * Trim whitespace around comma-separated includes.
     */
    #[Test]
    public function it_trims_whitespace_around_comma_separated_includes(): void
    {
        // Arrange

        $request = IncludeQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['include' => ' posts , team ']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame(['posts', 'team'], $request->includes());
    }

    /**
     * Reject unknown include keys with an allow-list message.
     */
    #[Test]
    public function it_rejects_unknown_include_keys_with_an_allow_list_message(): void
    {
        // Arrange

        $request = IncludeQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['include' => 'unknown']),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertTrue($validator->errors()->has('include'));
        $this->assertStringContainsString('Unsupported Include', $validator->errors()->first('include'));
    }

    /**
     * Return an empty list when include is omitted.
     */
    #[Test]
    public function it_returns_an_empty_list_when_include_is_omitted(): void
    {
        // Arrange

        $request = IncludeQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET'),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame([], $request->includes());
    }
}

/**
 * Minimal harness exposing include validation for unit tests.
 */
final class IncludeQueryParamHarness extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------​
    | Traits
    |--------------------------------------------------------------------------​
    */

    use ParsesIncludeQueryParam;

    /*
    |--------------------------------------------------------------------------​
    | Public
    |--------------------------------------------------------------------------​
    */

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the include query-param rules
     */
    public function rules(): array
    {
        return $this->includeQueryParamRules();
    }

    /**
     * Attach include allow-list validation to the validator under test.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateIncludeQueryParam($validator);
    }

    /**
     * Relation keys callers may request via `?include=`.
     *
     * @return list<string> the allowed include keys
     */
    protected function allowedIncludeKeys(): array
    {
        return ['posts', 'team'];
    }
}
