# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.10.0] - 2026-09-08

### Added

- **E-Mail verification flow** (ported and improved from the internal reference build): registration
  queues a temporary signed verification link (`AUTH_VERIFICATION_EXPIRE`, default 60 minutes) via a
  config-driven SPA destination (`API_EMAIL_VERIFICATION_URL`); `GET /api/auth/email/verify/{id}/{hash}`
  validates the signature and expiry, binds the link to its mailbox with a constant-time hash compare,
  and redirects the browser to the SPA result page with `verified=1|0`; `POST /api/auth/email/resend`
  re-sends for the authenticated account only (no enumeration or spam surface) and is rate limited on
  a User ID + IP composite key. Business routes answer **403** to unverified accounts while logout,
  `GET /me`, resending, and ending the current session stay reachable. New audit events:
  `Email Verification Sent`, `Email Verified`, `Email Verification Failed` (recorded on tampered or
  foreign-mailbox link attempts with the attempted address).
- `POST /api/teams`, `PATCH /api/teams/{team}`, and `DELETE /api/teams/{team}` - Admin-only Team
  management (`teams.create`, `teams.update`, `teams.delete`); deletion is refused with `422` while the
  Team still has assigned Users so members are never silently un-scoped to `team_id` null
- `role` on `PATCH /api/users/{user}` - Admin-only role assignment (`users.assign-role`, `Admin` /
  Manager / `User`) following the `team_id` field-gating pattern; self role changes and service-account
  role changes answer `403`, demoting the last Admin answers `422`
- `GET /api/sessions/{web_session}` and `DELETE /api/sessions/others` - single-session show with the
  standard `fields` / `include` contract (out-of-scope rows answer `404` via the scoped binding) and
  "sign out other devices" (`sessions.revoke-own`, bearer tokens untouched, current browser stays signed in)
- `POST /api/csp-reports` - public browser CSP violation receiver (legacy `report-uri` and modern
  Reporting API shapes, always `204`, `413` past 16 KiB, dedicated `csp-reports` log channel and per-IP
  throttle); both CSP policies now carry `report-uri` pointing at it
- `/.well-known/security.txt` (RFC 9116) served by route with a `security_txt` config path, plus a root
  `SECURITY.md` disclosure policy; the suite fails the build when `Expires` goes stale
- Optional E.164 `phone` on users (`POST` / `PATCH /api/users`, canonical form enforced by the new
  `E164PhoneNumber` rule backed by libphonenumber, exposed through `fields[users]`); the `E164Phone`
  support helper also ships `normalize()` for a future SMS two-factor channel
- `EnvExampleParityTest` - asserts application config keys are documented in `.env.example` and that
  `.env.ci` mirrors the same section banners and key contract

### Changed

- `.env.example` and `.env.ci` restructured to the `create-env-file` skill: purpose headers, canonical
  section order (GeoIP and product features before Frontend; security edge last), `(required)` /
  `(optional)` markers, unset-fallback comments on every commented key, and a full-mirror CI contract
- `.gitignore` - `.env.local` and `.env.*.local` patterns so personal overlay files cannot be committed

## [1.9.0] - 2026-09-06

### Added

- `GET /api/status` - public System Status page: current state and daily uptime history per monitored component (`database`, `cache`, `queue`), with worst-reading `overall_status` rollup and a `days` window query param (1-90, default 90); rate limited per IP via a dedicated `api-status` limiter (`API_STATUS_RATE_LIMIT_PER_MINUTE`, default 30)
- `system_health_checks` table, `SystemHealthCheck` model and factory, and the `health:record` console command (scheduled every five minutes except in `local` and `testing`, `withoutOverlapping()`) - runs every tagged check and persists one row per component per run with a shared `checked_at` instant
- System Health subsystem: `SystemHealthCheck` contract, `SystemHealthStatus` enum, `SystemHealthCheckResult` DTO, `SystemHealthCheckRegistry` (container-tagged checks), `SystemHealthServiceProvider`, `DatabaseHealthCheck` (`select 1` with a 500 ms degraded threshold), `CacheHealthCheck` (throwaway-key round trip), `QueueHealthCheck` (sync reports Up; otherwise resolves the connection without dispatching a job), `SystemHealthHistoryQuery` (all aggregation in SQL), `ShowSystemStatusRequest`, and `SystemStatusController` - plus unit and feature tests and the `/status` OpenAPI path, schemas, and example
- `POST /api/auth/forgot-password` and `POST /api/auth/reset-password` - broker-based account recovery with enumeration-neutral responses and a shared `api-auth-password` rate limiter (composite email+IP plus per-IP ceiling)
- `ResetUserPasswordAction` - full credential rotation on reset: Personal Access Tokens revoked, web sessions stamped revoked with post-commit payload destruction, remember token rotated, `session_version` bumped
- `ResetPasswordNotification` (queued, config-driven SPA destination via `API_PASSWORD_RESET_URL`) and `PasswordChangedNotification` (queued security alert with parsed request details), plus the `PasswordChangeSource` enum
- `Password Reset Requested` and `Password Reset` audit events, a `sendPasswordResetNotification()` override on the User model, and new forgot/reset feature and notification unit tests
- Self-service password hardening (`PATCH /api/me/password`): new password now enforces the shared `Password::defaults()` policy with `PasswordMaxLength` and the new `api.password_max_length` config, rejects a password identical to the current one, and dispatches the `PasswordChangedNotification` security alert (`PasswordChangeSource::SelfService`) after a successful change
- Pluggable user-agent parser (`config/useragent.php`, `basic` and `null` drivers) - web sessions registered without an explicit `device_name` now derive one from the parsed user agent
- Fail-closed session revocation: `InvalidateStoredSessionAction` returns the store's destroy result, logs a truncated SHA-256 fingerprint instead of the raw session ID, and `failClosed()` bumps `session_version` so a store failure still recalls every cookie; adopted by the password-change, logout, surgical-revoke, and password-reset flows
- Session activity tracking: `TouchWebSessionActivityAction` and the `session.touch` middleware refresh `last_activity_at` on cookie sessions at most every five minutes; `session.version` exposes a shared `SESSION_KEY` constant
- `POST /api/auth/forgot-password` and reset flow added to `scripts/pen-test-auth.sh` (enumeration, rate limit, token binding, replay, credential rotation, audit) plus a session-activity probe, `docs/testing.md`, and the testing-doc link in `README.md`
- `config/hashing.php` - Argon2id as the default password hash driver with env-driven work factors and `HASH_REHASH_ON_LOGIN`
- Rehash-on-login in `AuthenticateUserAction` - transparently upgrades stale Argon2id hashes after work-factor bumps
- `RehashOnLoginTest` - covers stale-hash upgrade, suspended-user guard (no rehash), and unchanged current hashes
- GeoIP subsystem: the `GeoIpLocator` contract, the `GeoIpLocation` DTO, the `GeoIpDatabase` singleton (bind in `AppServiceProvider::register()`), and the fail-open `MaxMindGeoIpLocator` (private/reserved-range skip outside `local`, `geoip.local_fallback_ip` lookup in `local`, city capped at 255 characters, uppercased ISO 3166-1 alpha-2 country)
- `geoip:update` command - downloads GeoLite2-City with a 120 second timeout, validates the extracted MMDB by opening it with a `GeoIp2\Database\Reader` before touching the destination, and swaps it in with an atomic `rename()` on the destination filesystem; temp-dir cleanup and credential guards kept, no Octane runtime hint (this starter runs PHP-FPM/Sail)
- Weekly `geoip:update` schedule in `routes/console.php` - Sundays 03:15 UTC for `staging` and `production` only, `withoutOverlapping()`, skipped entirely when MaxMind credentials are not configured
- `location_city` and `location_country` columns on `web_sessions` and `auth_audit_logs` - written once per session registration (never on the activity heartbeat) and by the queued auth audit listener from the event IP, exposed on `WebSessionResource` behind the same `sessions.list-all` telemetry gate as `ip_address`/`user_agent` and as plain fields on `AuthAuditLogResource`, with sparse fieldset allow-lists, OpenAPI schemas, examples, and a `GeoIP` section in `.env.example`
- Full data retention policy: no scheduled pruning; every table (including `system_health_checks` and `auth_audit_logs`) retains its rows indefinitely by deliberate compliance decision, documented in `routes/console.php`
- `GET /health` request class (`ShowHealthRequest`) so the probe controller follows the every-controller-takes-a-FormRequest convention


### Fixed

- `/api/docs` rendered permanent loading skeletons with an empty sidebar: the unpinned `Scalar.createApiReference()` CDN embed never hydrated its Introduction section. Replaced with the attribute-based embed pinned to `@scalar/api-reference@1.64.1`, and the docs CSP now allows Scalar's font origin (`fonts.scalar.com`)

- Queued event listeners registered twice: event discovery (`app/Listeners`) and the manual `Event::listen()` calls in [AppServiceProvider](app/Providers/AppServiceProvider.php) both registered `RecordAuthAuditLog` and `SendTwoFactorCodeNotification`, so every audit row was written twice and every two-factor e-mail was queued twice. Manual registrations removed; discovery is the single source of registration

### Changed

- Security hardening from a full adversarial audit (banking-grade review of auth, recovery, platform, and data layers): Sanctum token abilities are now enforced at request time (`User::can()` composes Spatie permissions with the token's abilities, so a client scoped to `['roles.list']` can no longer reach `users.list-all` data); deactivating an API client revokes its live bearer tokens; the demo API client is only seeded in `local`/`testing` and re-seeding no longer resurrects a revoked client; session-bound two-factor pendings expire with the configured TTL instead of living as long as the session; failed password-reset attempts are recorded in the audit log (`Password Reset Failed`); every response ships `Cache-Control: no-store, private`; `AuthAuditLogPolicy` enforces the `audit-logs.list` permission instead of a bare role check; user email sort/search are gated behind `users.view-email` (enumeration oracle closed); trusted-proxy support via `TRUSTED_PROXIES` (default trusts nothing); `/health` and `/api/status` throttled per IP; boot-time guard rejects reflective CORS with credentials; token ability arrays capped at 50; docs CSP tightened (no `unsafe-inline` scripts) with SRI pinning of the Scalar bundle; `npm audit` advisory (nanoid) resolved
- All env files (`.env`, `.env.example`, `.env.ci`, `.env.testing.local`) restructured with pipe-style section dividers and section bodies; removed unused variables (`MEMCACHED_HOST`, the unused `AWS_*` S3 block, and `BROADCAST_CONNECTION` - nothing in the codebase broadcasts)
- `routes/api.php` regrouped into labelled sections (Public Authentication / Authenticated API); `scripts/pen-test-auth.sh` section headlines now Title Case
- `.env.example`, `.env.ci`, and `phpunit.xml` - aligned low-cost Argon2id settings for tests and CI
- Login timing-normalisation docs and config comments - dummy hash uses the configured driver, not bcrypt-only wording
- Documentation and comment sweep: full PHPDoc on every factory and support method (summary, `@param`, `@return`), block comments replace stacked `//` paragraphs, `ID` title-cased in prose, stale porting references removed, route divider bodies restored to Title Case, and `pint.json` keeps explicit `@return` tags (`no_superfluous_phpdoc_tags: false`)


## [1.8.1] - 2026-08-28

### Added

- Semgrep SAST CI job with Laravel security rules (`scripts/semgrep.sh`)

### Changed

- HTTP request classes use semantic section dividers instead of generic Public/Protected blocks
- Session cookie defaults: `domain` defaults to `null`, `secure` defaults to `true` (`SESSION_SECURE_COOKIE=false` in `.env.example` and `.env.ci` for local HTTP)
- Dependabot cooldown (`default-days: 7`) and npm `min-release-age=7` for Semgrep supply-chain checks
- Dev dependencies: laravel/framework 13.29.0, phpunit 13.3.2, mockery 1.6.15, phpstan 2.2.9, Symfony 8.1.5, and related transitive bumps

## [1.8.0] - 2026-08-19

### Added

- `GET /api/sessions`, `DELETE /api/sessions/{web_session}`, and `DELETE /api/sessions/current` - cookie-bound web session registry with admin `sessions.list-all` / `sessions.revoke-any` support
- `web_sessions` table, `WebSession` model, and `RegisterWebSessionAction` - sessions registered at the privilege boundary (login, two-factor completion, remember-me restore)
- `sessions.list-own`, `sessions.list-all`, `sessions.revoke-own`, and `sessions.revoke-any` permissions (permission catalog count is now 25)
- Session telemetry gating in `WebSessionResource` - `user_id` and cross-user `ip_address` / `user_agent` require `sessions.list-all`
- Adversarial pen-test coverage for the sessions surface in `scripts/pen-test-auth.sh`

### Changed

- Auth routes moved under `/api/auth/*` (`/login`, `/register`, `/two-factor/*`, `/login/remember`); global logout remains `POST /api/logout`
- Global logout revokes every `web_sessions` registry row via `RevokeAllWebSessionsForUserAction`
- CLI and CI diagnostic copy uses Title Case headlines per project conventions
- Dev dependencies: phpunit 13.3.1, laravel/pao 1.1.4, mockery 1.6.13, laravel/pint 1.30.5, laravel/sail 1.67.0

### Fixed

- `scripts/verify-openapi-examples.sh` restores `${BASE}` on auth path curls so the OpenAPI examples job does not abort on malformed URLs
- `scripts/pen-test-auth.sh` falls back to seeded registry rows when cookie login cannot complete for MFA-enrolled accounts

## [1.7.0] - 2026-08-17

### Added

- `GET /api/auth/two-factor/status` - poll pending two-factor challenge expiry for session-bound and stateless clients (`GetTwoFactorStatusAction`)
- Separate rate limiters for two-factor send, verify, and status polling (`API_TWO_FACTOR_SEND_*`, `API_TWO_FACTOR_VERIFY_*`, `API_TWO_FACTOR_STATUS_RATE_LIMIT_PER_MINUTE`)
- Per-route Content Security Policy - strict default for API responses, relaxed Scalar/jsDelivr policy for `GET /api/docs`; CSP omitted in `local` while Vite hot-reload is active

### Changed

- Suspending a User now revokes every Sanctum token, bumps `session_version`, and clears sessions inside the same database transaction
- Stale `session_version` on cookie sessions logs the User out, invalidates the session, and regenerates the CSRF token
- `session_version` is stamped when authentication completes rather than auto-stamped by middleware on first request
- Bearer-authenticated requests skip the session-version gate even when an `Origin` header binds a session cookie
- PHPUnit enforces CSP in the testing environment (`SECURITY_CSP_ENFORCE=true`)

### Fixed

- User-Agent values in auth audit logs are capped at 1024 characters in `RecordAuthAuditAction` (single normalisation path for direct and queued writes)
- DB-touching Action and listener tests moved from `tests/Unit/` to `tests/Feature/` to match the no-database unit-test contract

## [1.6.0] - 2026-08-17

### Added

- Email two-factor authentication - `POST /api/auth/two-factor/send` and `POST /api/auth/two-factor/verify` complete sign-in after valid credentials when `mfa_method` is set; opaque `two_factor_token` supports stateless clients
- `POST /api/users` - admin user creation with role and optional `team_id` (`users.create`); new accounts auto-enrol in email MFA
- `PATCH /api/clients/{client}` - update API client name, abilities, and active status (`api-clients.update`)
- `users.create` and `api-clients.update` permissions; permission catalog count is now 21
- `mfa_method` column on users (migration); `MfaMethod` enum; audit events for two-factor issued, verified, and failed
- `FinaliseAuthenticatedSessionAction` - shared remember-me, token issuance, and login audit for password and two-factor completion flows
- Queued `TwoFactorChallengeIssued` event and `SendTwoFactorCodeNotification` listener for off-request email delivery
- Configurable two-factor TTLs and attempt limits (`API_TWO_FACTOR_CODE_TTL_SECONDS`, `API_TWO_FACTOR_PENDING_TTL_SECONDS`, `API_TWO_FACTOR_MAX_ATTEMPTS`)

### Changed

- `POST /api/auth/register` and MFA-enrolled `POST /api/auth/login` return `two_factor_required` and `two_factor_token` instead of an immediate bearer token - complete send/verify before a session is issued
- Email addresses are normalised to lowercase on register, login, admin user creation, and API client service-user emails
- OpenAPI, README, and `docs/permissions.md` updated for two-factor flow, user creation, and client PATCH

## [1.5.0] - 2026-08-16

### Added

- `PATCH /api/me` - self-service profile update (`name` only; `email`, `password`, and `team_id` are prohibited)
- `PATCH /api/me/password` - self-service password change (requires the current password and rotates the User's sessions)
- `GET /api/teams` and `GET /api/teams/{team}` - read-only Team index and show with the standard sort, `fields[teams]`, and `filter[search]` contract (`teams.list` on Admin and Manager)
- `POST /api/users/{user}/suspend` and `POST /api/users/{user}/unsuspend` - admin account suspension (`users.suspend`); suspended identities are turned away on every authenticated route
- `GET /health` - public uptime probe (no auth) reporting database reachability and the application version

### Changed

- Application version now derives from the `version` field in `composer.json` via `config('app.version')` (override with `APP_VERSION`); `/health` reports it
- CI adds a `composer verify:version` gate - fails when `docs/openapi.yaml` drifts from `composer.json`

### Fixed

- OAuth client-credentials tokens honour their own expiration instead of falling back to the user Personal Access Token lifetime

## [1.4.0] - 2026-08-16

### Added

- Public `POST /api/auth/login` and `POST /api/auth/register` endpoints - password auth with Sanctum
  bearer tokens, generic invalid-credential responses, and `api-auth` rate limiting
  (10 requests / minute per IP and email)
- `POST /api/logout` - revokes every Sanctum token, clears remember-me state, deletes all
  server-side session rows for the User, and invalidates the current request session when
  present
- `auth_audit_logs` table and `RecordAuthAuditAction` - audit trail for login, failed
  login, logout, registration, and remember-me session restoration
- Remember-me on `POST /api/auth/login` (`remember: true`) - extended PAT lifetime, rotated
  `remember_token`, web-guard remember cookie; `POST /api/auth/login/remember` for SPA re-auth
- `POST /api/users/logout` - admin-only force-logout by User ID; revokes every Sanctum
  token, clears remember-me state, and deletes all server-side session rows for each target
- OAuth2 client-credentials flow - `POST /api/oauth/token` exchanges `client_id` and
  `client_secret` for scoped Sanctum tokens; `api_clients` table, `Service` role, service
  User accounts (`is_service_account`), admin `GET|POST|DELETE /api/clients`, `GET /api/clients/{client}`,
  and demo seeded
  client `demo-integration-client`
- `GET /api/audit-logs` and `GET /api/audit-logs/{auth_audit_log}` - admin read-only
  auth audit log index and show with search, event, user, and API client filters
  plus `include=user` on list and show
- `GET /api/permissions` - read-only Spatie permission catalog for token and API
  client ability pickers (`permissions.list` on Admin, Manager, and User)
- `GET /api/me` - caller profile without `users.list`
- `AuthenticatedUserResource` for login and registration responses (always includes email
  for the session owner)
- Account suspension (`suspended_at`) with `active.account` middleware and adversarial
  `scripts/pen-test-auth.sh` coverage

### Changed

- OpenAPI spec synced with current routes (`GET /permissions`, audit log show,
  client show), suspension behaviour, and live response examples
- Suspended accounts are rejected at login and OAuth client-credentials exchange
  with the same generic `Invalid Credentials` message as wrong passwords - they
  no longer receive a bearer token that only fails on the next API call
- Registration duplicate-email validation returns the generic `Invalid Credentials` message
  (same as login) so callers cannot enumerate accounts via `/register`
- Login credential checks run a dummy password hash when the email is unknown to reduce
  timing side-channels that reveal account existence
- Stateful remember-me login refreshes the User before `Auth::login()` so the remember
  recaller cookie is queued on the response

## [1.3.1] - 2026-08-12

### Added

- Release runbook in `docs/releasing.md` - changelog, quality gates, tag, and GitHub publish steps

### Changed

- README visual polish - centred header badges, Mermaid pipeline diagram, GitHub callouts, expanded testing and quality-gate notes

## [1.3.0] - 2026-08-12

### Changed

- Rename index helpers from `List*` to `Index*`: `IndexSort`, `IndexSortQuery`, `IndexFieldsQuery`, `IndexSortParser`; FormRequest accessor `indexSort()` (was `listSort()`)
- Extract `SearchTermParser` for `filter[search]` normalisation; remove trait harness unit tests and `tests/Support/` classes - coverage via Support unit tests and feature/resource tests on real hosts
- Bump `spatie/laravel-permission` to ^8.3, `phpunit/phpunit` to ^13.3, and `laravel/telescope` to ^5.22.1

## [1.2.1] - 2026-08-11

### Changed

- Bump GitHub Actions to v7; add Dependabot for Actions and sync `.env.ci` with API rate-limit and CORS settings
- Harden CI workflow concurrency and retrigger pipeline after GitHub Actions outage
- Add weekly Dependabot version updates for Composer (`open-pull-requests-limit: 10`)

### Fixed

- Fix `v1.2.0` changelog compare link footer

## [1.2.0] - 2026-08-06

### Added

- `IndexSortParser` and `AllowList` Support helpers for query-param parsing and allow-list comparison
- Unit tests for `IndexSortParser` and `AllowList` with adversarial `#[DataProvider]` coverage
- CORS configuration (`config/cors.php`) and `ApiCorsTest` feature coverage for browser clients
- Personal Access Token expiration via `API_TOKEN_EXPIRATION_DAYS` (default 90), synced to Sanctum config
- Feature tests for expired Sanctum tokens and token `expires_at` on creation

### Changed

- FormRequest parse traits delegate grammar to Support classes; traits retain HTTP wiring only
- Removed redundant FormRequest harness unit tests - fields, include, and sort wiring covered by feature tests
- OpenAPI `TokensIndexSuccess` example uses nullable `last_used_at` for newly issued tokens
- OpenAPI verify script pre-creates index tokens and requests `sort=-id` for stable envelopes

### Fixed

- PHPStan types in `StoreTokenControllerTest` for token expiration assertions

## [1.1.0] - 2026-08-06

### Added

- Unit tests for API Resources (`UserResource`, `RoleResource`, `PermissionResource`, `TeamResource`, `PersonalAccessTokenResource`) and `SerialisesSparseAttributes`
- Unit tests for FormRequest concerns (`ParsesFieldsQueryParam`, `ParsesIncludeQueryParam`, `ParsesSearchQueryParam`, `SanitisesPlainTextAttributes`, `ValidatesTokenPayload`)
- Unit tests for Support helpers (`CommaSeparatedList`, `ApiExceptionRenderer`, `ApiDateTime`, `AllowListValidation`, `ApiResponse`, `LikePattern`, `PlainText`, `QualifiedColumn`)
- Feature tests for Sanctum authentication, API token rate limiting, and security probes (SQL injection, mass assignment, boundary inputs)
- Feature tests for `UserPolicy` edge cases and expanded controller coverage
- CI job verifying OpenAPI component examples against live API responses (`composer verify:openapi`)
- `fields[roles]` sparse-fieldset validation on `UserIndexRequest` and matching invalid-query coverage

### Changed

- FormRequest concerns aligned with `ApiFormRequest` base and ai-rules conventions (`ParsesSearchQueryParam` `@mixin`, dash-underline test groups, PHPDoc on every `#[Test]`)
- Test suite reorganised: removed `#[CoversTrait]` from feature tests; concern and resource tests use dedicated unit namespaces
- `scripts/verify-openapi-examples.sh` supports CI via `ARTISAN_CMD` and `OPENAPI_VERIFY_BASE` environment variables

## [1.0.0] - 2026-08-06

### Added

- Laravel 13 API skeleton with Sanctum bearer authentication and Spatie roles and permissions
- Users, Roles, and Tokens resources with a consistent query-driven index pattern
- OpenAPI specification, permissions reference, and performance notes
- CI quality gates: Pint, Larastan level 9, PHPUnit with 90% line-coverage gate, and `composer audit`
- Laravel Sail setup with MySQL and Redis for local development

[Unreleased]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.10.0...HEAD
[1.10.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.9.0...v1.10.0
[1.9.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.8.1...v1.9.0
[1.8.1]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Aontaigh/laravel-api-skeleton/releases/tag/v1.0.0
