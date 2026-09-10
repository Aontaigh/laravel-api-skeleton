<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Webhooks;

use App\Services\Webhooks\WebhookSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for webhook payload signing and verification.
 */
#[CoversClass(WebhookSigner::class)]
final class WebhookSignerTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Frame the signature with the version prefix.
     */
    #[Test]
    public function it_frames_the_signature_with_the_version_prefix(): void
    {
        // Arrange

        $signer = new WebhookSigner;

        // Act

        $signature = $signer->sign('secret', 'delivery-uuid', 1700000000, '{"id":"delivery-uuid"}');

        // Assert

        $this->assertStringStartsWith('v1,', $signature);
        $this->assertSame(
            'v1,'.hash_hmac('sha256', 'delivery-uuid.1700000000.{"id":"delivery-uuid"}', 'secret'),
            $signature,
        );
    }

    /**
     * Verify a signature produced by the signer.
     */
    #[Test]
    public function it_verifies_a_signature_it_produced(): void
    {
        // Arrange

        $signer = new WebhookSigner;
        $signature = $signer->sign('secret', 'delivery-uuid', 1700000000, '{"id":"delivery-uuid"}');

        // Act + Assert

        $this->assertTrue($signer->verify('secret', 'delivery-uuid', 1700000000, '{"id":"delivery-uuid"}', $signature));
    }

    /**
     * Reject a tampered body with a constant-time comparison.
     */
    #[Test]
    public function it_rejects_a_tampered_body(): void
    {
        // Arrange

        $signer = new WebhookSigner;
        $signature = $signer->sign('secret', 'delivery-uuid', 1700000000, '{"id":"delivery-uuid"}');

        // Act + Assert

        $this->assertFalse($signer->verify('secret', 'delivery-uuid', 1700000000, '{"id":"tampered"}', $signature));
        $this->assertFalse($signer->verify('wrong-secret', 'delivery-uuid', 1700000000, '{"id":"delivery-uuid"}', $signature));
        $this->assertFalse($signer->verify('secret', 'other-uuid', 1700000000, '{"id":"delivery-uuid"}', $signature));
    }

    /**
     * Encode payloads with stable flags so creation and sending sign the same string.
     */
    #[Test]
    public function it_encodes_payloads_deterministically(): void
    {
        // Arrange

        $signer = new WebhookSigner;

        $payload = ['id' => 'uuid', 'event' => 'user.created', 'data' => ['url' => 'https://example.com/a?b=c']];

        // Act

        $first = $signer->encode($payload);

        // Assert

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($first, true);

        $this->assertSame($first, $signer->encode($decoded));
        $this->assertStringContainsString('https://example.com/a?b=c', $first);
    }
}
