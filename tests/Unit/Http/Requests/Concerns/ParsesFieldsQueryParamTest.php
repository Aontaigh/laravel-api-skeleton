<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ParsesFieldsQueryParam;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for sparse fieldset (`fields[{resource}]`) parsing and allow-list validation.
 */
#[CoversTrait(ParsesFieldsQueryParam::class)]
final class ParsesFieldsQueryParamTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Parse and filter fields by the allow list.
     */
    #[Test]
    public function it_parses_and_filters_fields_by_the_allow_list(): void
    {
        // Arrange

        $request = FieldsQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['fields' => ['users' => 'id,name,email']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame(['id', 'name', 'email'], $request->fieldsFor('users'));
    }

    /**
     * Trim whitespace around comma-separated fields.
     */
    #[Test]
    public function it_trims_whitespace_around_comma_separated_fields(): void
    {
        // Arrange

        $request = FieldsQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['fields' => ['users' => ' id , name ']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertSame(['id', 'name'], $request->fieldsFor('users'));
    }

    /**
     * Reject unknown field columns with an allow-list message.
     */
    #[Test]
    public function it_rejects_unknown_field_columns_with_an_allow_list_message(): void
    {
        // Arrange

        $request = FieldsQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['fields' => ['users' => 'id,unknown']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertTrue($validator->errors()->has('fields.users'));
        $this->assertStringContainsString('Unsupported Field', $validator->errors()->first('fields.users'));
    }

    /**
     * Reject unknown fields resource keys.
     */
    #[Test]
    public function it_rejects_unknown_fields_resource_keys(): void
    {
        // Arrange

        $request = FieldsQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET', ['fields' => ['posts' => 'id']]),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertTrue($validator->errors()->has('fields.posts'));
        $this->assertStringContainsString('Unsupported Fields Resource', $validator->errors()->first('fields.posts'));
    }

    /**
     * Return null when fields are omitted.
     */
    #[Test]
    public function it_returns_null_when_fields_are_omitted(): void
    {
        // Arrange

        $request = FieldsQueryParamHarness::createFrom(
            Request::create('/api/users', 'GET'),
        )->setContainer($this->app);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/api/users', []));

        $validator = validator($request->query->all(), $request->rules());
        $request->setValidator($validator);
        $request->withValidator($validator);

        // Act

        $validator->passes();

        // Assert

        $this->assertNull($request->fieldsFor('users'));
    }
}

/**
 * Minimal harness exposing sparse-fieldset validation for unit tests.
 */
final class FieldsQueryParamHarness extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------​
    | Traits
    |--------------------------------------------------------------------------​
    */

    use ParsesFieldsQueryParam;

    /*
    |--------------------------------------------------------------------------​
    | Public
    |--------------------------------------------------------------------------​
    */

    /**
     * Validation rules for the harness request.
     *
     * @return array<string, array<int, string>> the fields query-param rules
     */
    public function rules(): array
    {
        return array_merge(
            $this->fieldsQueryParamRules('users'),
            ['fields' => ['sometimes', 'array']],
        );
    }

    /**
     * Attach fields allow-list validation to the validator under test.
     *
     * @param Validator $validator the validator under extension
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateFieldsQueryParam($validator, 'users');
        $this->validateFieldsKeys($validator);
    }

    /**
     * Resource keys callers may use under `fields[…]`.
     *
     * @return list<string> the allowed fields resource keys
     */
    protected function allowedFieldsResourceKeys(): array
    {
        return ['users', 'teams'];
    }

    /**
     * Columns callers may request for a given `fields[…]` resource key.
     *
     * @param  string       $resourceKey the `fields[…]` key
     * @return list<string> the whitelisted column names for that resource
     */
    protected function allowedFieldsFor(string $resourceKey): array
    {
        return match ($resourceKey) {
            'users' => ['id', 'name', 'email'],
            'teams' => ['id', 'name'],
            default => [],
        };
    }
}
