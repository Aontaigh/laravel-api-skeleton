<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ParsesSearchQueryParam;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the `filter[search]` query-param parsing and validation.
 */
#[CoversTrait(ParsesSearchQueryParam::class)]
final class ParsesSearchQueryParamTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the trimmed search term.
     */
    #[Test]
    public function it_returns_the_trimmed_search_term(): void
    {
        // Arrange

        $request = SearchQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['filter' => ['search' => '  alice  ']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame('alice', $request->searchTerm());
    }

    /**
     * Return null when search is omitted.
     */
    #[Test]
    public function it_returns_null_when_search_is_omitted(): void
    {
        // Arrange

        $request = SearchQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET'),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertNull($request->searchTerm());
    }

    /**
     * Return null for an empty string after trimming.
     */
    #[Test]
    public function it_returns_null_for_an_empty_string_after_trimming(): void
    {
        // Arrange

        $request = SearchQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['filter' => ['search' => '   ']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertNull($request->searchTerm());
    }

    /**
     * Enforce a max length of 255 characters.
     */
    #[Test]
    public function it_enforces_a_max_length_of_255_characters(): void
    {
        // Arrange

        $request = SearchQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['filter' => ['search' => str_repeat('x', 256)]]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertTrue($validator->errors()->has('filter.search'));
    }

    /**
     * Accept a search term of exactly 255 characters.
     */
    #[Test]
    public function it_accepts_a_search_term_of_exactly_255_characters(): void
    {
        // Arrange

        $request = SearchQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['filter' => ['search' => str_repeat('x', 255)]]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertFalse($validator->errors()->has('filter.search'));
    }
}

/**
 * Minimal harness exposing search filter validation for unit tests.
 */
final class SearchQueryParamHarness extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------​
    | Traits
    |--------------------------------------------------------------------------​
    */

    use ParsesSearchQueryParam;

    /*
    |--------------------------------------------------------------------------​
    | Public
    |--------------------------------------------------------------------------​
    */

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the search filter rules
     */
    public function rules(): array
    {
        return $this->searchFilterRules();
    }
}
