<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Enums\AuthAuditEvent;
use App\Http\Middleware\EnsureRequestId;
use App\Support\RequestId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for request correlation ID propagation.
 */
#[CoversClass(EnsureRequestId::class)]
#[CoversClass(RequestId::class)]
final class EnsureRequestIdTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Echo a caller-supplied request ID back on the response.
     */
    #[Test]
    public function it_echoes_a_caller_supplied_request_id(): void
    {
        // Act

        $response = $this->withHeaders(['X-Request-ID' => 'caller-id-123'])
            ->getJson('/api/app-info');

        // Assert

        $response->assertOk();
        $response->assertHeader('X-Request-ID', 'caller-id-123');
    }

    /**
     * Mint and return a fresh ID when the caller sends none.
     */
    #[Test]
    public function it_mints_a_fresh_id_without_one(): void
    {
        // Act

        $response = $this->getJson('/api/app-info');

        // Assert

        $response->assertOk();

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertIsString($requestId);
        $this->assertTrue(RequestId::isValid($requestId));
    }

    /**
     * Replace a hostile ID with a fresh one instead of echoing it.
     */
    #[Test]
    public function it_replaces_a_hostile_id_with_a_fresh_one(): void
    {
        // Act

        $response = $this->withHeaders(['X-Request-ID' => "abc\ndef"])
            ->getJson('/api/app-info');

        // Assert

        $response->assertOk();

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertIsString($requestId);
        $this->assertStringNotContainsString("\n", $requestId);
        $this->assertTrue(RequestId::isValid($requestId));
    }

    /**
     * Carry the echoed ID into the audit row for the request.
     */
    #[Test]
    public function it_carries_the_id_into_audit_rows(): void
    {
        // Act

        $response = $this->withHeaders(['X-Request-ID' => 'audit-trace-1'])
            ->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        // Assert

        $response->assertOk();

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::PasswordResetRequested->value,
            'request_id' => 'audit-trace-1',
        ]);
    }
}
