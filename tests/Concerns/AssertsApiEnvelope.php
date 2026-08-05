<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * Shared assertions for the standard API response envelope.
 */
trait AssertsApiEnvelope
{
    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Assert the response is a 422 validation error in the ApiResponse envelope.
     *
     * @param TestResponse<\Illuminate\Http\JsonResponse> $response          the HTTP response
     * @param list<string>                                $expectedErrorKeys the dotted validation keys that must be present
     */
    protected function assertApiValidationErrors(TestResponse $response, array $expectedErrorKeys): void
    {
        $response->assertUnprocessable();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 422);
        $response->assertJsonPath('message', 'Validation Failed');
        $response->assertJsonPath('data', null);

        foreach ($expectedErrorKeys as $key) {
            $response->assertJsonStructure([
                'meta' => [
                    'errors' => [
                        $key => [],
                    ],
                ],
            ]);
        }
    }

    /**
     * Assert the response is an error in the standard ApiResponse envelope.
     *
     * @param TestResponse<\Illuminate\Http\JsonResponse> $response   the HTTP response
     * @param int                                         $statusCode the expected HTTP status code
     * @param string                                      $message    the expected Title Case message
     */
    protected function assertApiErrorEnvelope(
        TestResponse $response,
        int $statusCode,
        string $message,
    ): void {
        $response->assertStatus($statusCode);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', $statusCode);
        $response->assertJsonPath('message', $message);
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta', []);
    }

    /**
     * Return the decoded `meta.allowed` hints from a validation error response.
     *
     * @param  TestResponse<\Illuminate\Http\JsonResponse> $response the HTTP response
     * @return array<string, list<string>>                 the supported values keyed by validation field
     */
    protected function apiMetaAllowed(TestResponse $response): array
    {
        $allowed = $response->json('meta.allowed');

        if (! is_array($allowed)) {
            $this->fail('Expected `meta.allowed` To Be an Array');
        }

        $typed = [];

        foreach ($allowed as $key => $values) {
            if (! is_string($key) || ! is_array($values)) {
                $this->fail('Expected `meta.allowed` To Be a Map of String Keys to String Lists');
            }

            $list = [];

            foreach ($values as $value) {
                if (! is_string($value)) {
                    $this->fail('Expected `meta.allowed` Values To Be Strings');
                }

                $list[] = $value;
            }

            $typed[$key] = $list;
        }

        return $typed;
    }

    /**
     * Return the decoded `meta.errors` bag from a validation error response.
     *
     * @param  TestResponse<\Illuminate\Http\JsonResponse> $response the HTTP response
     * @return array<string, list<string>>                 the validation messages keyed by field
     */
    protected function apiMetaErrors(TestResponse $response): array
    {
        $errors = $response->json('meta.errors');

        if (! is_array($errors)) {
            $this->fail('Expected `meta.errors` To Be an Array');
        }

        $typed = [];

        foreach ($errors as $key => $messages) {
            if (! is_string($key) || ! is_array($messages)) {
                $this->fail('Expected `meta.errors` To Be a Map of String Keys to String Lists');
            }

            $list = [];

            foreach ($messages as $message) {
                if (! is_string($message)) {
                    $this->fail('Expected `meta.errors` Values To Be Strings');
                }

                $list[] = $message;
            }

            $typed[$key] = $list;
        }

        return $typed;
    }

    /**
     * Return the decoded `data` array from a paginated list response.
     *
     * @param  TestResponse<\Illuminate\Http\JsonResponse> $response the HTTP response
     * @return list<array<string, mixed>>                  the resource items in `data`
     */
    protected function apiDataItems(TestResponse $response): array
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            $this->fail('Expected `data` To Be an Array');
        }

        $items = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                $this->fail('Expected Each `data` Item To Be an Array');
            }

            $record = [];

            foreach ($item as $key => $value) {
                if (! is_string($key)) {
                    $this->fail('Expected Each `data` Item Key To Be a String');
                }

                $record[$key] = $value;
            }

            $items[] = $record;
        }

        return $items;
    }
}
