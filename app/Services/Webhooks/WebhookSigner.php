<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

/**
 * Computes and verifies Svix-style webhook payload signatures.
 *
 * Wire format: `v1,{hex}`, where hex is `HMAC_SHA256(secret, "{id}.{timestamp}.{body}")`.
 * The delivery UUID binds the signature to one attempt row, and the timestamp
 * lets receivers enforce a freshness window against replayed captures.
 */
final class WebhookSigner
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Signature version prefix framing every signature value. */
    public const string VERSION_PREFIX = 'v1';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Encode a delivery payload to its canonical JSON body.
     *
     * One encoder for creation and sending: the signature covers this exact
     * string, so re-encoding with different flags at send time would break
     * verification. Key order follows insertion order - builders must construct
     * the array in wire order (`id`, `event`, `occurred_at`, `data`).
     *
     * @param  array<string, mixed> $payload the delivery payload
     * @return string               the canonical JSON body
     */
    public function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Sign a delivery body for the given delivery UUID and timestamp.
     *
     * @example
     * $signature = $signer->sign($secret, $deliveryUuid, $timestamp, $body);
     * // 'v1,9f2c…'
     *
     * @param  string $secret       the endpoint's plaintext signing secret
     * @param  string $deliveryUuid the delivery UUID the signature binds to
     * @param  int    $timestamp    the Unix timestamp the signature binds to
     * @param  string $body         the exact JSON body about to be sent
     * @return string the framed signature value for the `Webhook-Signature` header
     */
    public function sign(string $secret, string $deliveryUuid, int $timestamp, string $body): string
    {
        return self::VERSION_PREFIX.','.hash_hmac(
            'sha256',
            "{$deliveryUuid}.{$timestamp}.{$body}",
            $secret,
        );
    }

    /**
     * Verify a received signature with a constant-time comparison.
     *
     * @param  string $secret       the endpoint's plaintext signing secret
     * @param  string $deliveryUuid the `Webhook-Id` header value
     * @param  int    $timestamp    the `Webhook-Timestamp` header value
     * @param  string $body         the raw received body
     * @param  string $signature    the received `Webhook-Signature` value
     * @return bool   true when the signature matches
     */
    public function verify(string $secret, string $deliveryUuid, int $timestamp, string $body, string $signature): bool
    {
        return hash_equals($this->sign($secret, $deliveryUuid, $timestamp, $body), $signature);
    }
}
