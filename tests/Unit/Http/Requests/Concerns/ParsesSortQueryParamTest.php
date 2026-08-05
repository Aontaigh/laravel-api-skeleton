<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ParsesSortQueryParam;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for Sort Query-Param allow-list validation.
 */
#[CoversTrait(ParsesSortQueryParam::class)]
final class ParsesSortQueryParamTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_rejects_a_double_descending_prefix_and_records_supported_columns(): void
    {
        // Arrange

        $request = SortQueryParamHarness::createFrom(
            Request::create('/api/users?sort=--name', 'GET'),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertTrue($validator->errors()->has('sort'));
        $this->assertStringContainsString('Supported:', $validator->errors()->first('sort'));
        $this->assertSame(['created_at', 'id', 'name'], $request->exposedAllowListHints()['sort'] ?? null);
    }
}

/**
 * Minimal harness exposing protected sort validation for unit tests.
 */
final class SortQueryParamHarness extends ApiFormRequest
{
    use ParsesSortQueryParam;

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the sort query-param rules
     */
    public function rules(): array
    {
        return $this->sortQueryParamRules();
    }

    /**
     * Attach sort allow-list validation to the validator under test.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateSortQueryParam($validator);
    }

    /**
     * Expose recorded allow-list hints for assertions.
     *
     * @return array<string, list<string>> the hints keyed by validation field
     */
    public function exposedAllowListHints(): array
    {
        $reflection = new \ReflectionProperty(ApiFormRequest::class, 'allowListHints');
        $value = $reflection->getValue($this);

        if (! is_array($value)) {
            return [];
        }

        $hints = [];

        foreach ($value as $key => $entries) {
            if (! is_string($key) || ! is_array($entries)) {
                continue;
            }

            $list = [];

            foreach ($entries as $entry) {
                if (is_string($entry)) {
                    $list[] = $entry;
                }
            }

            $hints[$key] = $list;
        }

        return $hints;
    }

    /**
     * Sort columns callers may request via `?sort=`.
     *
     * @return list<string> the allowed sort columns
     */
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'created_at'];
    }
}
