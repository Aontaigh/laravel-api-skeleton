<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the ApiResponse envelope.
 *
 * Pure response-building logic, so these run with no database.
 */
#[CoversClass(ApiResponse::class)]
final class ApiResponseTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Build a success envelope.
     */
    #[Test]
    public function it_builds_a_success_envelope(): void
    {
        // Act

        $response = ApiResponse::success(data: ['id' => 1], message: 'Retrieved Successfully');

        // Assert

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'status' => 'success',
            'status_code' => 200,
            'message' => 'Retrieved Successfully',
            'data' => ['id' => 1],
            'meta' => [],
        ], $response->getData(true));
    }

    /**
     * Build an error envelope with the default status code.
     */
    #[Test]
    public function it_builds_an_error_envelope_with_the_default_status_code(): void
    {
        // Act

        $response = ApiResponse::error(message: 'Something Went Wrong');

        // Assert

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'status' => 'error',
            'status_code' => 400,
            'message' => 'Something Went Wrong',
            'data' => null,
            'meta' => [],
        ], $response->getData(true));
    }

    /**
     * Build an error envelope with a custom status code and meta.
     */
    #[Test]
    public function it_builds_an_error_envelope_with_a_custom_status_code_and_meta(): void
    {
        // Act

        $response = ApiResponse::error(
            message: 'Resource Not Found',
            statusCode: 404,
            meta: ['error_code' => 'not_found'],
        );

        // Assert

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame([
            'status' => 'error',
            'status_code' => 404,
            'message' => 'Resource Not Found',
            'data' => null,
            'meta' => ['error_code' => 'not_found'],
        ], $response->getData(true));
    }

    /**
     * Build a validation error envelope with allow-list hints.
     */
    #[Test]
    public function it_builds_a_validation_error_envelope_with_allow_list_hints(): void
    {
        // Arrange

        $validator = validator(
            ['sort' => 'bad'],
            ['sort' => ['required', 'string']],
        );
        $validator->errors()->add('sort', 'Unsupported Sort Column: bad');

        $exception = new \Illuminate\Validation\ValidationException($validator);

        // Act

        $response = ApiResponse::validationError($exception, [
            'sort' => ['created_at', 'id', 'name'],
        ]);

        // Assert

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'status' => 'error',
            'status_code' => 422,
            'message' => 'Validation Failed',
            'data' => null,
            'meta' => [
                'errors' => ['sort' => ['Unsupported Sort Column: bad']],
                'allowed' => ['sort' => ['created_at', 'id', 'name']],
            ],
        ], $response->getData(true));
    }
}
