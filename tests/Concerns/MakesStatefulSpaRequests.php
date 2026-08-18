<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

/**
 * Stateful SPA request helpers for Sanctum cookie authentication tests.
 */
trait MakesStatefulSpaRequests
{
    /**
     * Stateful SPA request headers for Sanctum cookie authentication.
     *
     * @param  string|null           $xsrfToken optional CSRF token for mutating requests
     * @return array<string, string> the request headers
     */
    protected function statefulRequestHeaders(?string $xsrfToken = null): array
    {
        $headers = [
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ];

        if ($xsrfToken !== null) {
            $headers['X-XSRF-TOKEN'] = $xsrfToken;
        }

        return $headers;
    }

    /**
     * Start a stateful SPA session and return the CSRF token for mutating requests.
     *
     * @param  array<string, string> $statefulHeaders the SPA origin headers
     * @return string                the decrypted CSRF token
     */
    protected function beginStatefulSession(array $statefulHeaders): string
    {
        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withCredentials()
            ->withHeaders($statefulHeaders)
            ->get('/sanctum/csrf-cookie');

        $response->assertNoContent();

        $this->storeResponseCookies($response);

        $cookie = $response->getCookie('XSRF-TOKEN');
        $this->assertNotNull($cookie);

        return $this->requireNonEmptyString(
            $cookie->getValue(),
            'CSRF cookie value missing',
        );
    }

    /**
     * Persist decrypted response cookies for subsequent stateful requests.
     *
     * @param TestResponse<JsonResponse> $response the HTTP test response
     */
    protected function storeResponseCookies(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $decrypted = $response->getCookie($cookie->getName());

            if ($decrypted !== null) {
                $this->withCookie(
                    $decrypted->getName(),
                    $this->requireNonEmptyString(
                        $decrypted->getValue(),
                        'Response cookie value missing',
                    ),
                );
            }
        }
    }

    /**
     * Require a non-empty string for cookie and CSRF assertions.
     *
     * @param  string|null $value   the value to validate
     * @param  string      $message the failure message when empty
     * @return string      the validated non-empty string
     */
    protected function requireNonEmptyString(?string $value, string $message): string
    {
        if ($value === null || $value === '') {
            $this->fail($message);
        }

        return $value;
    }
}
