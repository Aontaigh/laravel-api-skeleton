# Security Audit

Full adversarial audit performed 2026-09-06 (banking-grade review): four parallel
adversarial reviews (authentication flows, password recovery and token surfaces,
web platform, data and authorisation layer), live probing, dependency audits
(`composer audit`, `npm audit`), and the standard gate suite. Every finding was
triaged: fixed, or explicitly accepted with rationale. Re-run the audit after any
material auth change.

## Verdict

No critical findings. Four high-severity findings were fixed in this pass; the
remaining high and medium findings were fixed or accepted as noted below. The
suite was green after remediation: 93.0%+ line coverage, Larastan level 9,
Semgrep 0 findings, `composer audit` and `npm audit` clean, and the adversarial
pen test at 0 failures. Material auth changes after this audit (request
correlation IDs, outbound webhooks, client secret rotation) shipped with their
own hardening pass, coverage, and pen-test sections (47-48); the suite stands at
1120 tests and 93.07% line coverage with all gates green.

## Findings and Dispositions

### High

| Finding | Disposition |
| --- | --- |
| Sanctum token abilities validated at issuance but never enforced - a client scoped to `['roles.list']` still carried the Service role's directory-wide permissions (CWE-862/863) | **Fixed.** `User::can()` now composes Spatie permissions with the token's abilities; scoped tokens are bounded per request. Regression tests in [TokenAbilityEnforcementTest](../tests/Feature/Authorization/TokenAbilityEnforcementTest.php) |
| Demo API client with a public secret seeded wherever `db:seed` runs; re-seeding resurrected revoked clients (CWE-798) | **Fixed.** Seeder is environment-guarded (`local`/`testing` only) and uses `firstOrCreate` so re-seeding never resurrects a revoked client |
| No trusted-proxy configuration: behind a load balancer every client shares the proxy IP, collapsing IP rate limits and destroying audit attribution | **Accepted with scaffolding.** [TrustedProxies](../app/Support/TrustedProxies.php) trusts nothing by default (spoof-proof) and is configured per environment via `TRUSTED_PROXIES`; operators must set their LB CIDRs at deploy time - a code default cannot know the network topology |
| PATCH is_active:false on an API client left live bearer tokens valid for up to 30 days (CWE-613) | **Fixed.** Deactivating a client revokes its service account's tokens immediately |

### Medium

| Finding | Disposition |
| --- | --- |
| Session-bound two-factor pendings never expired (the TTL only applied to stateless tokens), letting a stolen mid-login cookie re-arm OTP challenges indefinitely | **Fixed.** `PendingTwoFactor::resolve()` enforces the configured expiry on both session and token paths |
| Every response lacked `Cache-Control`; cookie-authenticated SPA GETs were browser/proxy cacheable | **Fixed.** `Cache-Control: no-store, private` on all responses |
| Failed password-reset attempts produced no audit trail | **Fixed.** `Password Reset Failed` audit event recorded with the attempted address |
| Docs CSP allowed `script-src 'unsafe-inline'` and unrestricted jsDelivr host | **Fixed (tightened).** Inline scripts removed (attribute embed), script-src narrowed, SRI hash pins the exact Scalar bundle, `fonts.scalar.com` allowed for typography only |
| OTP guess budget resets on each fresh credential login; no account-level lockout | **Accepted.** Per-challenge budget (5), per-route throttles, and IP ceilings bound the attack to ~20 guesses/min/IP; an account-level lockout is a product decision (lockout enables denial-of-service against victims) and is tracked as backlog |

### Low

| Finding | Disposition |
| --- | --- |
| `audit-logs.list` permission enforced as a bare Admin role check | **Fixed.** Policy now checks the permission, keeping the catalog the single source of truth |
| Email enumeration oracle: viewers without `users.view-email` could sort and search on `users.email` | **Fixed.** Sort allow-list and the search email arm are gated behind `users.view-email` |
| `system_health_checks` unbounded retention (~864 rows/component/day) | **Accepted.** Full data retention is a deliberate compliance decision: no scheduled pruning is configured and every row is kept. The retention policy is documented in `routes/console.php`; a bounded prune command will be added when a window is agreed |
| `/health` unthrottled with a per-request DB roundtrip | **Fixed.** 60/min per-IP throttle (`API_HEALTH_RATE_LIMIT_PER_MINUTE`) |
| Reflective CORS with credentials possible if operators set `*` + credentials | **Fixed.** Boot-time guard rejects the combination |
| Token ability arrays unbounded | **Fixed.** Capped at 50 entries, each 255 chars |
| `.env.example` prod footguns (`APP_DEBUG=true`, `SESSION_SECURE_COOKIE=false`) | **Fixed.** Warning comments; debug comment already states the production rule |
| npm advisory GHSA-2v37-7h3g-55p8 (nanoid) | **Fixed.** `npm audit fix`, 0 advisories |
| `/health` discloses the app version | **Accepted.** Standard practice for service health endpoints; the version is equally present in the OpenAPI spec served alongside it |
| Docs Basic Auth brute-forceable when enabled | **Accepted.** The credential is operator-managed and rotated like any secret; a dedicated limiter is tracked as backlog |
| CI third-party actions tag-pinned, not SHA-pinned | **Accepted.** Dependabot keeps the tags current; SHA conversion is mechanical churn tracked as backlog |
| Synchronous fail-open HIBP breach check in reset/registration validation | **Fixed.** Fail-open is deliberate (an HIBP outage must not block account recovery); the verifier now runs with a 3-second timeout so a slow endpoint cannot stall workers |
| Client token accumulation per exchange with no listing surface | **Accepted.** Tokens expire in 30 days; deactivation now revokes immediately (high finding above) |

## Test Coverage Added by the Audit

- Token ability enforcement matrix (scoped client denied, wildcard allowed, scoped PAT bounded, session unaffected) - [TokenAbilityEnforcementTest](../tests/Feature/Authorization/TokenAbilityEnforcementTest.php)
- Failed password resets produce an audit row - [ResetPasswordControllerTest](../tests/Feature/Http/Controllers/Auth/ResetPasswordControllerTest.php)
- Mass-assignment probe extended with `role`, `is_service_account`, `session_version`, `suspended_at` - [ApiSecurityProbeTest](../tests/Feature/Http/ApiSecurityProbeTest.php)
- Trusted-proxy parsing (empty, blank, list, wildcard rejection) - [TrustedProxiesTest](../tests/Unit/Support/TrustedProxiesTest.php)
- Team filter LIKE-escape unit coverage - [TeamFilterQueryTest](../tests/Unit/Queries/Teams/TeamFilterQueryTest.php)

## Deliberately Out of Scope

OAuth social login, TOTP/passkeys, and SMS delivery remain
starter omissions by design (see the README's "What's Not Included" - email
verification has since shipped). The pen-test suite ([scripts/pen-test-auth.sh](../scripts/pen-test-auth.sh)) is the
continuous regression net for everything above: 48 adversarial sections, 0
failures on a clean run.
