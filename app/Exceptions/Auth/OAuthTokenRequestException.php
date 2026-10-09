<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * An OAuth 2.0 token-endpoint failure, carrying the wire codes RFC 6749 requires.
 *
 * Standard: [RFC 6749 section 5.2](https://www.rfc-editor.org/rfc/rfc6749#section-5.2), which
 * requires the token endpoint to answer with `400 Bad Request` and a JSON body of `error` plus an
 * optional `error_description`. The codes are fixed by the RFC and are not ours to rename:
 *
 * - `invalid_request` - a required parameter is missing or malformed
 * - `invalid_client` - client authentication failed
 * - `invalid_grant` - the grant or credential is otherwise invalid
 * - `unsupported_grant_type` - the grant type is not supported
 *
 * Every major provider implements exactly this shape. Stripe returns
 * `{"error":"invalid_grant","error_description":"Authorization code does not exist: ..."}`
 * ([Connect OAuth reference](https://docs.stripe.com/connect/oauth-reference)), Authlib ships a
 * dedicated `InvalidClientError` bound to `RFC6749#section-5.2`
 * ([Authlib spec notes](https://docs.authlib.org/en/v0.15.3/specs/rfc6749.html)), and Keycloak,
 * Okta, and Microsoft Entra ID all use the same `error` / `error_description` pair.
 *
 * Why this exists rather than reusing a validation failure: a token endpoint that answers `422`
 * with `meta.errors` breaks any spec-conformant OAuth client, because those clients read
 * `error` at the top level to decide between retrying, re-authenticating, and giving up. The
 * envelope stays correct everywhere else on this API; this one endpoint is an interoperability
 * surface owned by the RFC.
 *
 * `401` versus `400` follows the RFC's own rule: `invalid_client` is `401` **with** a
 * `WWW-Authenticate` challenge when the client attempted authentication via the `Authorization`
 * header, and `400` otherwise. This app authenticates machine clients from request-body
 * credentials, so the `400` branch is what applies, and the header is still emitted to advertise
 * the supported scheme.
 *
 * Diagnostic copy follows php-quality (CLI and Diagnostic Errors): Title Case headlines, no
 * trailing full stop; detail after a colon when needed.
 */
final class OAuthTokenRequestException extends RuntimeException
{
    /**
     * @param string $errorCode   the RFC 6749 error code, for example `invalid_client`
     * @param string $description the human-readable detail sent as `error_description`
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly string $description,
    ) {
        parent::__construct('OAuth Token Request Failed: '.$errorCode);
    }

    /**
     * The RFC 6749 error code, for example `invalid_client`.
     *
     * Named `errorCode` rather than `code` because `Exception::$code` is an inherited `int`, and a
     * redeclared property of the same name is incompatible with it.
     */
    public function code(): string
    {
        return $this->errorCode;
    }

    /**
     * Client authentication failed, so no token can be issued.
     *
     * Deliberately generic in its description. An attacker probing `client_id` values must not be
     * able to tell an unknown client from a wrong secret, so this covers both.
     */
    public static function invalidClient(): self
    {
        return new self('invalid_client', 'Client Authentication Failed');
    }

    /**
     * A required parameter is missing or malformed.
     *
     * @param string $parameter the offending parameter name
     */
    public static function invalidRequest(string $parameter): self
    {
        return new self('invalid_request', 'Missing Or Invalid Parameter: '.$parameter);
    }

    /**
     * The grant type is not one this endpoint implements.
     *
     * @param string $grantType the unsupported grant type
     */
    public static function unsupportedGrantType(string $grantType): self
    {
        return new self('unsupported_grant_type', 'Unsupported Grant Type: '.$grantType);
    }

    /**
     * The client authenticated but is not permitted a token right now.
     *
     * Used for a verified secret the application then declines on policy grounds, such as a
     * suspended owner. Kept distinct from `invalid_client` in the audit trail, but the description
     * stays as generic as `invalid_client`'s so the response reveals nothing.
     */
    public static function invalidGrant(): self
    {
        return new self('invalid_grant', 'Client Authentication Failed');
    }
}
