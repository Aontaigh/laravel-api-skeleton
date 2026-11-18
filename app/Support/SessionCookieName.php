<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Resolves the session cookie name, applying the `__Host-` prefix only where a
 * browser will actually accept it.
 *
 * `__Host-` is the strongest scoping signal a cookie can carry: a browser
 * accepts it only when the cookie is `Secure`, sent with `Path=/` and carries no
 * `Domain` attribute, and in exchange refuses to let any sibling subdomain set
 * or overwrite it. That closes the cookie-tossing route where a compromised or
 * attacker-controlled subdomain plants a session identifier the parent
 * application then trusts.
 *
 * The prefix is derived from the resolved deployment flags rather than
 * hard-coded because an illegal `__Host-` cookie is not corrected by the
 * browser: it is dropped silently, so the symptom is every sign-in failing with
 * no error anywhere. Plain-HTTP local development and any deployment that pins
 * `SESSION_DOMAIN` or a non-root `SESSION_PATH` therefore keep the unprefixed
 * name.
 */
final class SessionCookieName
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * The browser-enforced prefix for a host-only, `Secure`, `Path=/` cookie.
     *
     * A browser accepts a cookie carrying this prefix only when it is `Secure`,
     * scoped to `Path=/` and sent without a `Domain` attribute, and in exchange
     * refuses to let any sibling subdomain write the same name. The literal
     * comes from the cookie specification (`draft-ietf-httpbis-rfc6265bis`,
     * "Cookie Name Prefixes") rather than from a local convention, so it must
     * not be altered.
     *
     * @var string
     */
    public const string HOST_PREFIX = '__Host-';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Build the session cookie name for the given deployment flags.
     *
     * @param  bool        $secure  whether the cookie is marked `Secure`
     * @param  string|null $domain  the configured `SESSION_DOMAIN`, or null when unset
     * @param  string      $path    the configured `SESSION_PATH`
     * @param  string      $appName the application name the slug is derived from
     * @return string      the prefixed name when the prefix is legal, the plain name otherwise
     */
    public static function resolve(bool $secure, ?string $domain, string $path, string $appName): string
    {
        $slug = Str::slug($appName);

        $name = ($slug === '' ? 'laravel' : $slug).'-session';

        return $secure && self::isHostOnly($domain) && $path === '/'
            ? self::HOST_PREFIX.$name
            : $name;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the cookie will be written without a `Domain` attribute.
     *
     * An environment file that carries `SESSION_DOMAIN=` answers an empty
     * string rather than null, which browsers treat as no domain only after the
     * empty attribute is dropped. Both forms count as host-only here so a blank
     * line in a template cannot cost the deployment its prefix.
     *
     * @param  string|null $domain the configured domain value
     * @return bool        true when the cookie is host-only
     */
    private static function isHostOnly(?string $domain): bool
    {
        return $domain === null || trim($domain) === '';
    }
}
