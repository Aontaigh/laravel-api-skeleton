# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-10-04

### Breaking Changes

- **Global Logout URL:** `POST /api/logout` removed; global logout is
  **`POST /api/auth/logout`** (Bearer token required). Update clients, SPAs, and
  automation still calling the old path before upgrading

### Added

- **Auth Audit Outcome Column:** `outcome` column on `auth_audit_logs` (nullable
  string, after `event`) with the [`AuditOutcome`](app/Enums/AuditOutcome.php) enum
  (`succeeded`, `failed`, `refused`) - the Microsoft Entra `result` / `resultReason`
  model. The outcome is recorded at every dispatch site: `Succeeded` on successful
  lifecycle events; `Failed` on every `*Failed` event (bad credentials, two-factor
  mismatch, reset-link delivery failure); `Refused` on deliberate policy blocks
  (suspended password login, service-account password login, service-account password
  reset request or completion, and skipped service-account forgot-password delivery).
  Rows written before the column exist stay `null`
- **Auth Audit Outcome API:** `outcome` on
  [`AuthAuditLogResource`](app/Http/Resources/AuthAuditLogResource.php) and the
  audit-logs sparse fieldset and sort allow-lists in
  [`AuthAuditLogQueryConstraints`](app/Queries/AuthAuditLogs/AuthAuditLogQueryConstraints.php),
  with the OpenAPI `AuthAuditLog` schema, index/show examples, and field documentation
  updated
- **Password Reset Token Revocation:**
  [`RevokePasswordResetTokensAction`](app/Actions/Auth/RevokePasswordResetTokensAction.php)
  deletes outstanding password-reset broker rows when a User is suspended,
  soft-deleted, or receives a real role change (no-op role round-trips are ignored),
  so a pending reset link cannot outlive the account state
- **Current Web Session Query:**
  [`CurrentWebSessionQuery`](app/Queries/Sessions/CurrentWebSessionQuery.php) resolves
  the caller's non-revoked web session row for `DELETE /api/sessions/current`
- **Service Account Login Refusal:**
  [`ServiceAccountAuthenticationException`](app/Exceptions/Auth/ServiceAccountAuthenticationException.php)
  for password login attempts against machine identities (HTTP stays generic 422;
  audit records `refused`)
- **Client Ineligibility Reason Column:** `client_ineligibility_reason` column on
  `auth_audit_logs` (nullable string, after `outcome`) with
  [`ClientIneligibilityReason`](app/Enums/ClientIneligibilityReason.php)
  (`human_owned`, `suspended_owner`, `missing_owner`) when an OAuth client-credentials
  secret verifies but policy declines the grant;
  [`ClientCredentialRefusedException`](app/Exceptions/Auth/ClientCredentialRefusedException.php)
  and [`ClientTokenExchangeController`](app/Http/Controllers/Auth/ClientTokenExchangeController.php)
  persist the reason on `ClientTokenExchangeFailed` rows with outcome `refused`. The
  column is forensic storage only - not on
  [`AuthAuditLogResource`](app/Http/Resources/AuthAuditLogResource.php) or OpenAPI yet
- **CI Link Check Job:** `composer lint:links` CI job for Markdown relative links and
  heading anchors; [`scripts/lint-links.sh`](scripts/lint-links.sh)
- **Auth Audit Log Integrity Tests:**
  [`AuthAuditLogIntegrityTest`](tests/Feature/Security/AuthAuditLogIntegrityTest.php)
  adversarial coverage for audit-row cardinality, single listener registration, GeoIP
  enrichment isolation, and event-time semantics on the authentication evidence trail

### Changed

- **Logout Audit Presenting Token:** logout audit rows record the presenting personal
  access token id when the caller used a bearer credential
  ([`LogoutController`](app/Http/Controllers/Auth/LogoutController.php))
- **Suspended Password Login:** suspended password login answers **`403 Account
  Suspended`** once the password verifies (same signal as `active.account`
  mid-session) instead of generic `Invalid Credentials`; the attempt audits as
  `LoginFailed` with outcome `refused`, and a suspended MFA-enrolled account is
  refused before a two-factor challenge opens. OAuth client-credentials exchange still
  answers generic `Invalid Credentials` on the wire while auditing refusals
  (including a suspended owner) with outcome `refused` and a populated
  `client_ineligibility_reason` when the secret verified
- **Service Account Boundaries:** service accounts cannot password-login, request or
  complete a password reset, or exchange client credentials when the linked User is
  not flagged `is_service_account`; [`UserPolicy`](app/Policies/UserPolicy.php)
  refuses `GET/PATCH /me` for machine identities;
  [`AppServiceProvider`](app/Providers/AppServiceProvider.php) route binding for
  `{user}` excludes machine rows so `/api/users/{id}` answers **`404`** (not
  **`403`**) for a service-account id, matching the Users index exclusion
- **API Client Token Abilities:** API client create/update use
  [`normalizeApiClientTokenAbilities`](app/Services/Permissions/PermissionAbilityCatalog.php)
  (wildcard `['*']` refused); changing a client's ability set revokes its live
  service-user tokens so integrations must re-exchange under the new scope
- **Users Index Scope:** [`UserFilterQuery`](app/Queries/Users/UserFilterQuery.php)
  excludes service accounts from the Users index (managed via `/api/clients`)
- **Forgot Password Audit Outcomes:**
  [`ForgotPasswordController`](app/Http/Controllers/Auth/ForgotPasswordController.php)
  derives the `Password Reset Requested` audit outcome from the delivery path -
  `succeeded`, `failed`, or `refused` for service accounts
- **Login Password Byte Length:** [`LoginRequest`](app/Http/Requests/Auth/LoginRequest.php)
  applies [`PasswordByteLength`](app/Rules/PasswordByteLength.php) on verify (72-byte
  hasher boundary) without a creation-time max-length cap that would lock out existing
  passwords
- **Documentation:** README onboarding table, API group summary, known limitations, and
  doc cross-links; [`docs/permissions.md`](docs/permissions.md) suspended-login vs OAuth
  wording; OpenAPI login/logout paths and audit `outcome` documentation
- **Auth Verification Scripts:**
  [`scripts/verify-openapi-examples.sh`](scripts/verify-openapi-examples.sh) and
  [`scripts/pen-test-auth.sh`](scripts/pen-test-auth.sh) aligned with auth logout path,
  suspended-login behaviour, privilege-drift probes (roles, scoped PATs, machine tokens),
  and lifecycle edges (reset broker vs role change, client deactivation, ability reorder
  idempotence)
- **Shared Link Gate:** `scripts/lint-links.sh` is now the canonical copy shared with
  `courseco-app` and `integrations-hub`, with repository-specific paths and exemptions
  isolated in one `Configuration` block. Per-repository differences are no longer edits
  to the gate's logic, so a fix here reaches the other repositories on the next sync
  instead of leaving three divergent copies
- **Link Gate Entry Point:** Markdown link checking runs through `composer lint:links`
  only; the **Link Check** CI job invokes that composer script (with
  `markdown-link-check` as an npm devDependency), so the gate has one owner instead of
  ad hoc npm scripts that can drift
- **Composer CI Chain:** `composer ci` runs `@lint:links`, `@semgrep`, and
  `@verify:openapi` after static analysis so a local chain cannot green-light a push
  while skipping link, SAST, or OpenAPI-example gates (the Semgrep CI job and
  [`scripts/semgrep.sh`](scripts/semgrep.sh) pre-date this release; what changed is
  enforcement in the composer entry point)
- **Environment Example Contract:** [`.env.example`](.env.example) and
  [`.env.ci`](.env.ci) document Compose host-port overrides (`APP_PORT`, `VITE_PORT`,
  `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`), optional `MYSQL_ATTR_SSL_CA`, a Sanctum
  section (`SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_TOKEN_PREFIX`), and drop unused
  `VITE_APP_NAME` (SPA runtime config lives at `/api/app-info`)

### Fixed

- **Role Round-Trip Leaves Stale Roles:** `PATCH /api/users/{user}` guarded
  `syncRoles` behind `hasRole`, so submitting a role the user already held skipped
  the assignment entirely. A user holding two roles kept the one the response
  denied, and multi-role users are reachable because `CreateUserAction` and
  `CreateApiClientAction` both use `assignRole`, which adds without removing. The
  revocation of an outstanding password-reset token stays scoped to a real change;
  only the assignment moved outside the guard, because revocation asks "did a
  privilege change?" while assignment asks "what is the role set now?"
  ([`UpdateUserAction`](app/Actions/Users/UpdateUserAction.php))
- **Link Gate Verdicts:** the gate now separates a dead link from an unreachable
  host. A `404` or `410` fails immediately; anything else is retried three times with
  backoff and then reported as unreachable rather than dead. Both verdicts exit
  non-zero, so nothing is waved through, but a rate-limited host no longer produces a
  "broken link" claim about a tree that has not changed
- **Link Gate Reporting:** a single dead link is now reported as one rather than two,
  and a broken-pipe warning no longer masks a checker crash as a pass
- **OAuth Token Exchange TTL:** the OAuth token-exchange response derives `expires_in`
  from the minted token's own `expires_at` ([`TokenTtl`](app/Support/TokenTtl.php))
  rather than recomputing the configured window, so the reported lifetime can never
  drift from what the caller holds
- **API Client Ability Deduplication:**
  [`UpdateApiClientAction`](app/Actions/ApiClients/UpdateApiClientAction.php) compares
  ability sets with deduplication before revoking service-user tokens, so duplicate
  strings in stored JSON cannot trigger a false revoke
- **Auth Audit GeoIP Fail-Open:** [`RecordAuthAuditLog`](app/Listeners/RecordAuthAuditLog.php)
  catches GeoIP lookup failures and persists the audit row without location enrichment,
  so an enrichment outage cannot discard authentication evidence

## [1.16.1] - 2026-09-29

### Changed

- **Framework Dependency Bumps:** Bump `laravel/framework` to 13.34.0, `larastan/larastan` to 3.12.2, `laravel/telescope` to 5.25.0,
  `laravel/sail` to 1.68.0, `laravel/pint` to 1.32.1, `phpunit/phpunit` to 13.3.6, and
  `giggsey/libphonenumber-for-php` to 9.0.40
- **CI Pin:** CI: pin `github/codeql-action/upload-sarif` to v4.38.1

## [1.16.0] - 2026-09-29

### Added

- **User Restore Endpoint:** `POST /api/users/{user}/restore` - [`RestoreUserController`](app/Http/Controllers/Users/RestoreUserController.php),
  [`RestoreUserAction`](app/Actions/Users/RestoreUserAction.php), and
  [`RestoreUserRequest`](app/Http/Requests/Users/RestoreUserRequest.php) restore a soft-deleted User;
  `users.restore` is seeded on the **Admin** role only (Managers retain `users.delete` but not restore)
- **User Index Filters:** User index filters `filter[status]=` (`active`, `suspended`, `deleted`) and `filter[role]=` (Spatie
  role name), wired through [`AppliesUserFilters`](app/Http/Requests/Concerns/Users/AppliesUserFilters.php)
  and [`UserFilterQuery`](app/Queries/Users/UserFilterQuery.php); `deleted` selects the trashed-only
  scope used to locate records eligible for restore
- **User Lifecycle Audit Events:** Auth audit events **User Deleted** and **User Restored** on [`DestroyUserController`](app/Http/Controllers/Users/DestroyUserController.php)
  and restore; privileged user lifecycle actions record `actor_user_id` on delete, suspend, and
  unsuspend; admin `POST /api/users/{user}/tokens` records the acting Admin when the target differs
- **User Restored Webhook:** Webhook event `user.restored` ([`WebhookEvent::UserRestored`](app/Enums/WebhookEvent.php))
- **Tokens API:** `POST /api/tokens` accepts optional `expires_at`: omitted keeps `API_TOKEN_EXPIRATION_DAYS`, an
  explicit future timestamp sets expiry, and explicit `null` opts the Token into never expiring
  (admin-issued tokens keep the configured default only)
- **App Info Endpoint:** `GET /api/app-info` exposes `data.auth.token_expiration_days` so clients can mirror the configured
  default lifetime (`0` means never expire by default)
- **Authenticated User Resource:** [`AuthenticatedUserResource`](app/Http/Resources/AuthenticatedUserResource.php) includes `roles` and
  `permissions` on auth success payloads (login, registration, remember-me, and two-factor verify)
  so the SPA can hide controls the server would still deny
- **Auth Rate Limit Key Safety Tests:** [`AuthRateLimitKeySafetyTest`](tests/Feature/Http/AuthRateLimitKeySafetyTest.php) - malformed
  credential shapes on auth endpoints answer validation instead of tripping the throttle key builder
- **Test Coverage Attributes:** [`EnvExampleParityTest`](tests/Unit/Config/EnvExampleParityTest.php),
  [`SessionRotationTest`](tests/Feature/Support/SessionRotationTest.php), and
  [`ApiCorsTest`](tests/Feature/Http/ApiCorsTest.php) declare `#[CoversNothing]` so the suite adheres
  to the PHPUnit test coverage convention without coupling to arbitrary classes

### Changed

- **Finalise Authenticated Session Action:** [`FinaliseAuthenticatedSessionAction`](app/Actions/Auth/FinaliseAuthenticatedSessionAction.php) logs
  the User into the web guard whenever the session is regenerated (stateful SPA sign-in), not only
  when `remember` is true; `remember` controls the remember-me cookie only
- **Token Index Default Sort:** default sort is `-created_at` (newest first) via
  [`TokenQueryConstraints`](app/Queries/Tokens/TokenQueryConstraints.php)
- **Sanctum:** [`config/sanctum.php`](config/sanctum.php) sets global `expiration` to `null` so Sanctum no longer
  caps or overrides the per-token `expires_at` written at issuance (unchanged
  [`CreatePersonalAccessTokenAction`](app/Actions/Tokens/CreatePersonalAccessTokenAction.php) behaviour,
  now authoritative end-to-end)
- **Scoped PAT Escalation Guard:** moves from [`StoreTokenRequest`](app/Http/Requests/Tokens/StoreTokenRequest.php)
  into [`PersonalAccessTokenPolicy::create`](app/Policies/PersonalAccessTokenPolicy.php) and
  `createForUser`, including admin issuance for another User
- **User Policy:** [`UserPolicy`](app/Policies/UserPolicy.php) - `restore` ability with team and service-account
  rules; suspend, unsuspend, and delete refuse service accounts; team-less viewers may view, update,
  or delete only their own row when they lack `users.list-all`
- **Forgot Password Controller:** [`ForgotPasswordController`](app/Http/Controllers/Auth/ForgotPasswordController.php) catches mail
  delivery failures, reports them, and still returns the generic success envelope so outages cannot
  reveal which addresses have accounts
- **Record Auth Audit Data Serialisation:** [`RecordAuthAuditData::withLocation`](app/DataTransferObjects/Auth/RecordAuthAuditData.php) reads
  `actorUserId` with `?? null` so jobs serialised before that property existed restore safely
- **Index Controller Docblocks:** controllers and FormRequest traits import `Builder` for `@var` phpdoc; rename empty trait
  pipe sections (**Abstract** to **Public** / **Protected**); reorder FormRequest regions to match
  project convention without behaviour changes
- **Documentation Sync:** OpenAPI, [README](README.md), and [permissions.md](docs/permissions.md) document restore, user index
  filters, token expiry semantics, default token sort, app-info `auth.token_expiration_days`, auth
  payload `roles` / `permissions`, webhook `user.restored`, and the full auth-audit `filter[event]` allow-list
- **Test Section Dividers:** standardised all controller test group comment blocks across 27 test
  files to British English (**Authorisation Tests** instead of American **Authorization Tests**)

### Fixed

- **Auth Rate-Limit Key Safety:** composite rate-limit keys no longer call `$request->string()` on credentials before validation;
  non-string `email` / `client_id` values degrade to the per-IP bucket instead of returning **500**
  from throttle middleware ([`AppServiceProvider::authCompositeKey`](app/Providers/AppServiceProvider.php))
- **Team-Less User Policy Scope:** Users without a team no longer implicitly pass `view`, `update`, or `delete` checks against every other
  team-less account when the viewer lacks `users.list-all`

## [1.15.4] - 2026-09-22

### Added

- **Show API Docs Request:** [`ShowApiDocsRequest`](app/Http/Requests/Api/ShowApiDocsRequest.php) and
  [`ShowOpenApiSpecRequest`](app/Http/Requests/Api/ShowOpenApiSpecRequest.php) - empty
  FormRequests for `GET /api/docs` and the served OpenAPI YAML so those controllers follow
  the FormRequest convention

### Changed

- **ShowApiDocsController:** `ShowApiDocsController` and `ShowOpenApiSpecController` type-hint the new request classes
- **Public FormRequest Sections:** Unauthenticated public FormRequests (`ShowHealth`, `ShowAppInfo`, CSP reports, security.txt,
  system status, e-mail verification) use the **Authorisation** pipe section instead of
  **Public**
- **Enum Divider Cleanup:** `MfaMethod` and `SystemHealthStatus` drop redundant **Public** dividers before `label()`
- **Test Setup Region Names:** rename **Setup** regions to **Setup / Teardown** where `tearDown()` is present
  (`UnitTestCase` and matching feature/unit tests)
- **API Clients Seeder:** [`ApiClientsSeeder`](database/seeders/ApiClientsSeeder.php) documents production no-op behaviour;
  `VerifyEmailController` documents the injected verification action

## [1.15.3] - 2026-09-16

### Changed

- **Larastan Level 10:** Raise Larastan/PHPStan to **level 10**; narrow the raw aggregate rows and the query-builder WHERE clauses in tests so the strictest level stays green

## [1.15.2] - 2026-09-16

### Changed

- **Development Dependency Bumps:** Bump development dependencies: `laravel/pint` to 1.31.0, `larastan/larastan` to 3.11.0, and `laravel/pao` to 1.1.5

## [1.15.1] - 2026-09-16

### Fixed

- **Query Constraints Docblocks:** Malformed and duplicated docblocks in the `app/Queries/*/*QueryConstraints.php` allow-list constants

## [1.15.0] - 2026-09-16

### Added

- **PasswordByteLength:** `PasswordByteLength` validation rule rejects passwords longer than bcrypt's 72-byte limit on registration, password reset, user creation, and password change
- **Actor User Id:** `actor_user_id` on `auth_audit_logs` records the authenticated actor behind a privileged action (admin force-logout, session revocation), so the actor is answerable from the table alone
- **AuthTimingHash:** `AuthTimingHash` resolves the login timing-normalisation hash lazily and memoises it, so an unset value is no longer hashed at boot
- **PresentingToken:** `PresentingToken` distinguishes a persisted Personal Access Token from a cookie-session credential
- **RequestId::current():** `RequestId::current()` memoises the correlation ID per request so the response header, logs, and audit rows all join on one value

### Changed

- **Force-Logout Audit Ordering:** revokes credentials before recording the audit row and attributes the acting admin
- **Soft-Delete Credential Revocation:** revokes tokens and web sessions inside the deletion transaction
- **Store Token Audit Resilience:** audit dispatch is wrapped so a queued-listener failure cannot abort the one-time token response
- **AuthenticateUserAction:** `AuthenticateUserAction` and `AuthenticateClientCredentialsAction` read the timing hash through `AuthTimingHash`
- **Scripts/pen-test-auth.sh:** `scripts/pen-test-auth.sh`, `scripts/semgrep.sh`, and `scripts/verify-openapi-examples.sh` follow the bash scripting standards; Semgrep scans the Laravel ruleset plus the shared custom rules

### Fixed

- **Session Versioning With Bearer Header:** Session versioning is enforced on a cookie session that also presents an `Authorization` header; a superseded session could previously skip the stamp check
- **Session Cookie Defaults:** Session cookies default to `Secure` and `SESSION_DOMAIN` to null
- **Fail-Closed Session Rotation:** Fail-closed session rotation skips a keyless `withDefault()` owner instead of issuing an unscoped `UPDATE` across every User row
- **Scoped Token Escalation Guard:** A scoped Personal Access Token can no longer mint a broader token through `POST /tokens`
- **Force-Logout ID Validation:** `ids` are validated as strict integers and rejected with a per-index message

## [1.14.3] - 2026-09-14

### Changed

- **Eloquent Model Casts:** Eloquent models: standardise `Casts` pipe sections, drop redundant `created_at` / `updated_at`
  casts (Laravel already treats timestamps as datetimes), and remove the empty `Team::casts()`
  method
- **BelongsTo WithDefault Placeholders:** BelongsTo relations on `ApiClient`, `User`, `WebSession`, `WebhookEndpoint`, and
  `WebhookDelivery` use `withDefault()` so missing parents resolve to placeholders instead of
  null
- **FormRequest Authorisation Spelling:** FormRequest section headers use **Authorisation** (British spelling) across the API request
  layer; `ApiFormRequest` gains a **Validation Response** region
- **Enum Cases Regions:** Domain enums: add **Cases** pipe sections (`AuthAuditEvent`, `MfaMethod`, `RoleName`,
  `WebhookEvent`, and related enums)
- **Credential Mutation Transactions:** `RevokeApiClientAction` and `LogoutUserAction` wrap token and credential mutations in
  database transactions
- **Pen-Test Script Standards:** `scripts/pen-test-auth.sh` aligns with bash scripting standards (`set -euo pipefail`,
  repo-root resolution, Setup/Helpers/Work/Summary dividers, ShellCheck-clean); fixes the
  section 35 headline that executed `session_version` via backticks

### Fixed

- **Orphan Client Credentials Guard:** `AuthenticateClientCredentialsAction` rejects client-credentials exchange when the linked
  service User is missing (including soft-deleted accounts behind `withDefault()` placeholders)
  with the generic invalid-credentials response instead of attempting token issuance

### Added

- **Client Credentials Unit Coverage:** Unit coverage for missing, soft-deleted, and suspended service Users in
  `AuthenticateClientCredentialsActionTest`; pen-test section 26 probes soft-deleted
  service accounts on `POST /api/oauth/token`

## [1.14.2] - 2026-09-11

### Changed

- **Telescope Bump:** Bump `laravel/telescope` from 5.22.1 to 5.23.0 (dev dependency) ([#10](https://github.com/Aontaigh/laravel-api-skeleton/pull/10))
- **Releasing.md:** [`docs/releasing.md`](docs/releasing.md) - document GitHub release note format (emoji section
  headings, unwrapped bullets, **Full Changelog** footer)

## [1.14.1] - 2026-09-11

### Changed

- **Renovate.json:** [`.github/renovate.json`](.github/renovate.json) - `minimumReleaseAge` at root scope
  (Semgrep-compliant without per-rule exceptions)
- **CI Workflow:** CI workflow-lint job validates Renovate config with `renovate-config-validator`

## [1.14.0] - 2026-09-11

### Added

- **Zizmor CI Job:** Zizmor supply-chain audit for GitHub Actions: CI job audits workflows against
  [`.github/zizmor.yml`](.github/zizmor.yml), which accepts ref-pins for first-party
  `actions/*` and requires full-commit SHA pins (tag kept as a trailing comment
  for Dependabot) for everything else - run locally with
  `zizmor --config .github/zizmor.yml .github/workflows/`
- **Workflow Lint CI Job:** Workflow lint CI job (`actionlint`) for `.github/workflows/`
- **Renovate.json:** [`.github/renovate.json`](.github/renovate.json) - scopes Renovate to Docker image
  pins in workflows (Dependabot continues to own `uses:` actions and Composer)

### Changed

- **CI Action Hash Pins:** All third-party `uses:` references in CI are hash-pinned (`shivammathur/setup-php`,
  `ramsey/composer-install`, `github/codeql-action`, `zizmorcore/zizmor-action`);
  every `actions/checkout` sets `persist-credentials: false`
- **CI Workflow:** CI workflow section headers use Title Case; Semgrep and Zizmor job comments clarify
  merge-gate behaviour

### Removed

- **Security Audit Doc Removal:** `docs/security-audit.md` - historical audit record removed; ongoing regression
  coverage lives in the pen-test suite and quality gates

## [1.13.0] - 2026-09-10

### Added

- **Outbound Webhooks:** Outbound webhooks: Admin-only `GET|POST /api/webhook-endpoints`, show, `PATCH`, `DELETE`,
  per-endpoint delivery history (`GET .../deliveries` with `filter[event]` / `filter[status]`),
  synthetic test pings (`POST .../test`, `202`), and one-time secret rotation (`POST .../rotate-secret`).
  Domain writes fan out through a queued listener into pending delivery rows, each sent by
  `DeliverWebhookJob` with exponential backoff (8 attempts, 1-hour cap), Svix-style HMAC signatures
  (`Webhook-Id`, `Webhook-Timestamp`, `Webhook-Signature: v1,…`), per-endpoint auto-disable after
  10 consecutive failures, and SSRF-screened target URLs (HTTPS-only, no private ranges, DNS-checked).
  Secrets are returned once and stored encrypted; receiving is at-least-once (`Webhook-Id` is the
  idempotency key). Subscribed events: `user.created`, `user.deleted`, `user.suspended`,
  `user.unsuspended`, `team.created`, `team.updated`, `team.deleted`. Covered by new unit,
  feature, job, and listener tests, pen-test section 47, and live OpenAPI example
  verification for every new endpoint
- **Client Secret Rotation:** `POST /api/clients/{client}/rotate-secret` - Admin-only client secret rotation
  (`api-clients.update`); the new plaintext secret is returned once, the old secret is rejected
  on the next `POST /api/oauth/token`, and live bearer tokens stay valid until natural expiry
  (deactivate the client to kill them immediately). The active flag is left alone so rotation
  never silently resumes a deactivated client. Audited as `Client Secret Rotated`, covered by
  pen-test section 48 and a live OpenAPI example verification
- **Webhook Permissions:** Four new permissions in the seeder and Admin role (`webhooks.list`, `webhooks.create`,
  `webhooks.update`, `webhooks.delete`), wired into the new `WebhookEndpointPolicy` and
  documented in the permissions matrix
- **Webhook Delivery Configuration:** Webhook delivery knobs are env-configurable: retry budget
  (`API_WEBHOOK_DELIVERY_MAX_ATTEMPTS`, default 8), per-attempt HTTP timeout
  (`API_WEBHOOK_DELIVERY_TIMEOUT_SECONDS`, default 10), auto-disable streak
  (`API_WEBHOOK_AUTO_DISABLE_AFTER_FAILURES`, default 10), and the outbound-write throttle -
  all documented in the new Webhooks sections of `.env.example` / `.env.ci` and the
  `config/api.php` delivery block, with the `WebhookDnsResolver` contract bound to the system
  resolver in `AppServiceProvider` for swap-in tests
- **OpenAPI Verify Client Reset:** `verify:openapi` now resets `last_used_at` on client 1 before replaying examples, so the
  `ClientShowSuccess` shape no longer depends on which gates ran before it
- **Webhook Delivery Hardening:** Webhook hardening from adversarial review: deliveries never follow redirects (a hostile
  receiver answering `302` to a private address would have turned the signed delivery into an
  internal-network probe); `failure_streak` uses an atomic increment so parallel delivery jobs
  cannot lose each other's failures; outbound-emitting routes (create, test ping, rotate secret)
  carry a dedicated per-Admin `api-webhooks` throttle (`API_WEBHOOK_RATE_LIMIT_PER_MINUTE`,
  default 10); endpoint create, update, delete, and secret rotation are audited
  (`Webhook Endpoint Created/Updated/Deleted`, `Webhook Secret Rotated`)

### Changed

- **User Update Authorisation Status:** Team reassignment and role-change denials now answer `403 Forbidden` instead of `422`: a caller
  without `users.reassign-team` or `users.assign-role` attempting the field is refused at the
  authorisation gate (`UpdateUserRequest::authorize()` delegating to the `reassignTeam` /
  `assignRole` Policy abilities) rather than as a field-level validation error - permission
  problems now carry the status clients' retry logic expects. Self-role changes and role changes
  on service accounts answer `403` on the same grounds.
- **Lazy Loading Prevention:** Lazy loading now fails loudly outside production (`Model::preventLazyLoading()` in
  `AppServiceProvider::boot()`): a missing eager load surfaces as an exception in local
  development and the test suite instead of a slow N+1 query discovered in production. All
  endpoints and Resources were audited against the gate - no uncaught lazy loads remain.
- **Request Concern Compile-Time Hosts:** Request-concern traits that read FormRequest input now declare their host dependencies as
  abstract methods (composed through `ReadsRequestInput`), so pulling a concern into a class
  that cannot satisfy it fails at compile time instead of on the first request
  (`ResolvesTwoFactorPending`, `NormalisesAuthEmail`, `NormalisesE164PhoneAttributes`,
  `SanitisesPlainTextAttributes`).
- **Query Constraints Docblocks:** Every `*QueryConstraints` allow-list constant and every bounded class constant now carries a
  docblock in the conventions shape - what the list or bound gates, and what breaks or is
  rejected when it changes - so allow-lists read as documented API surface rather than bare
  literals (`@var`-only docblocks removed).
- **Test Class Region Order:** classes restructured to the single-`Setup` region order (`Traits` → `Setup` → `Tests`):
  shared helpers moved out of stray regions into the one `Setup` block, `Traits` declared
  first, and duplicated divider blocks removed (`SecurityHeaders`, four session/auth/System
  Health test classes, `InvalidateStoredSessionActionTest`, `TouchWebSessionActivityTest`).
- **Docblock Compliance Sweep:** full `@return` docblocks on every production method, test hook, helper, migration, and seeder; redundant `@var` tags on
  constructor-typed assignments removed; inline `TestResponse` generics replaced with imported
  short names; `TelescopeServiceProvider` finalised with section dividers; contracts
  (`GeoIpLocator`, `SystemHealthCheck`) gained `Public` regions; bash scripts standardised on
  `#!/bin/bash` with `set -euo pipefail` everywhere.
- **Documentation Sync:** README gains the Webhooks resource, subscribed-events list, controller
  layout, and completed rate-limit row; the Authentication endpoint table's comments are
  re-aligned to one column and its rate-limit / demo-client / two-factor flow notes are
  grouped under Title Case subheadings instead of one paragraph; `docs/permissions.md` adds `WebhookEndpointPolicy` to
  the policy list; `docs/security-audit.md` refreshed to the current suite state (1120 tests,
  93.07% coverage, pen-test sections 47-48); `docs/testing.md` / `docs/releasing.md` broken
  relative links repaired; `docs/api.md` org URL and verify-command alignment.

### Fixed

- **SecurityHeaders Divider Cleanup:** adjacent `| Public` divider blocks in `SecurityHeaders` middleware removed.

## [1.12.0] - 2026-09-08

### Added

- **Request Correlation IDs: Every Response Carries X-Reques:** Request correlation IDs: every response carries `X-Request-ID` (caller-supplied value honoured
  when well-formed, W3C `traceparent` trace ID as fallback, fresh UUID otherwise - never
  authentication); the ID rides the log context and is persisted on `auth_audit_logs.request_id`
  so one value joins responses, logs, and audit rows

### Fixed

- **Service Account Email Verification:** Service accounts blocked by e-mail verification: the `email.verified` gate turned away every
  machine-to-machine bearer token with `E-Mail Not Verified`, silently disabling the OAuth
  client-credentials flow. Service accounts authenticate via client credentials and have no mailbox
  to verify, so they now pass the gate; only interactive Users are held until confirmed

## [1.11.0] - 2026-09-08

### Added

- **App Info Endpoint:** `GET /api/app-info` - public deploy-verification metadata (application, runtime, driver names, never
  secrets) sharing the status page throttle, with an OpenAPI schema and example
- **Focused Mutation Audit: the Log Now Records:** Focused mutation audit: the log now records password changes, role changes, suspensions,
  session revokes, token issuance and revocation, and API client lifecycle - credential, session,
  and access-control events only. Plain resource administration (user create/rename/delete, team
  CRUD) stays out by design so incident response is never buried under admin noise. Covered by a
  cross-cutting `AuthAuditCoverageTest` plus pen-test section 46 and OpenAPI verify checks
- **Split Auth Rate Limits: Login and Registration:** Split auth rate limits: login and registration keep the shared email+IP rate but carry separate
  per-IP ceilings (`API_AUTH_LOGIN_IP_CEILING_PER_MINUTE`, default 20, and
  `API_AUTH_REGISTER_IP_CEILING_PER_MINUTE`, default 10); new User ID + IP buckets for password
  change (`auth-password-change`) and session revokes (`auth-sessions-revoke`); two-factor status
  polling gains a per-IP ceiling; the email-verify ceiling tightens to 15
- **Centralised Input Bounds: Emailmaxlength and Passwordres:** Centralised input bounds: `EmailMaxLength` and `PasswordResetTokenMaxLength` helpers beside the
  existing `PasswordMaxLength` (config-driven `max` rules with Title Case copy on login, register,
  recovery, and admin creation; reset tokens bound to the broker's 64 characters); `Password::defaults()`
  now caps length so overlong input never reaches Argon2id, and the breach verifier runs with a
  3-second timeout so a slow HIBP endpoint cannot stall workers

### Changed

- **Phone E164 Normalisation:** `phone` fields now compact display forms to canonical E.164 in `prepareForValidation()` before the
  strict `E164PhoneNumber` rule runs (previously rejected outright); `composer.lock` now materialises
  the `giggsey/libphonenumber-for-php` dependency
- **CSP Reporting Headers:** CSP reporting hardened: `report-to` directive and `Reporting-Endpoints` / legacy `Report-To`
  headers so modern and older Chromium both report (no nonce minted: no served page runs inline scripts)
- **Breach Lookup Test Fakes:** Test hermetics: breach assertions fake HIBP via a shared `FakesBreachLookup` concern instead of
  touching the live endpoint
- **Documentation Accuracy Pass:** Documentation accuracy pass: new Web Sessions and Security Telemetry API sections, corrected
  `/health` throttling, role matrix, audit coverage, and suite counts across README, permissions,
  testing, and security-audit docs
- **Release Note Correction:** Corrected the v1.10.0 note: self and service-account role changes answer `422` (prohibited
  field), not `403`; behaviour never changed

## [1.10.0] - 2026-09-08

### Added

- **E-Mail Verification Flow:** **E-Mail verification flow** (ported and improved from the internal reference build): registration
  queues a temporary signed verification link (`AUTH_VERIFICATION_EXPIRE`, default 60 minutes) via a
  config-driven SPA destination (`API_EMAIL_VERIFICATION_URL`); `GET /api/auth/email/verify/{id}/{hash}`
  validates the signature and expiry, binds the link to its mailbox with a constant-time hash compare,
  and redirects the browser to the SPA result page with `verified=1|0`; `POST /api/auth/email/resend`
  re-sends for the authenticated account only (no enumeration or spam surface) and is rate limited on
  a User ID + IP composite key. Business routes answer **403** to unverified accounts while logout,
  `GET /me`, resending, and ending the current session stay reachable. New audit events:
  `Email Verification Sent`, `Email Verified`, `Email Verification Failed` (recorded on tampered or
  foreign-mailbox link attempts with the attempted address).
- **Teams API:** `POST /api/teams`, `PATCH /api/teams/{team}`, and `DELETE /api/teams/{team}` - Admin-only Team
  management (`teams.create`, `teams.update`, `teams.delete`); deletion is refused with `422` while the
  Team still has assigned Users so members are never silently un-scoped to `team_id` null
- **Role:** `role` on `PATCH /api/users/{user}` - Admin-only role assignment (`users.assign-role`, `Admin` /
  Manager / `User`) following the `team_id` field-gating pattern; self role changes and service-account
  role changes answer `403`, demoting the last Admin answers `422`
- **Web Sessions API:** `GET /api/sessions/{web_session}` and `DELETE /api/sessions/others` - single-session show with the
  standard `fields` / `include` contract (out-of-scope rows answer `404` via the scoped binding) and
  "sign out other devices" (`sessions.revoke-own`, bearer tokens untouched, current browser stays signed in)
- **CSP Reports Endpoint:** `POST /api/csp-reports` - public browser CSP violation receiver (legacy `report-uri` and modern
  Reporting API shapes, always `204`, `413` past 16 KiB, dedicated `csp-reports` log channel and per-IP
  throttle); both CSP policies now carry `report-uri` pointing at it
- **/.well-known/security.txt:** `/.well-known/security.txt` (RFC 9116) served by route with a `security_txt` config path, plus a root
  `SECURITY.md` disclosure policy; the suite fails the build when `Expires` goes stale
- **App Info Endpoint:** `GET /api/app-info` - public deploy-verification metadata (application, runtime, driver names, never
  secrets) sharing the status page throttle, with an OpenAPI schema and example
- **Focused Mutation Audit: the Log Now Records:** Focused mutation audit: the log now records password changes, role changes, suspensions,
  session revokes, token issuance and revocation, and API client lifecycle - credential, session,
  and access-control events only. Plain resource administration (user create/rename/delete, team
  CRUD) stays out by design so incident response is never buried under admin noise
- **Optional E.164 Phone on Users (`Post /:** Optional E.164 `phone` on users (`POST` / `PATCH /api/users`, canonical form enforced by the new
  `E164PhoneNumber` rule backed by libphonenumber, exposed through `fields[users]`); the `E164Phone`
  support helper also ships `normalize()` for a future SMS two-factor channel
- **Split Auth Rate Limits: Login and Registration:** Split auth rate limits: login and registration keep the shared email+IP rate but carry separate
  per-IP ceilings (`API_AUTH_LOGIN_IP_CEILING_PER_MINUTE`, default 20, and
  `API_AUTH_REGISTER_IP_CEILING_PER_MINUTE`, default 10); new User ID + IP buckets for password
  change (`auth-password-change`) and session revokes (`auth-sessions-revoke`); two-factor status
  polling gains a per-IP ceiling; the email-verify ceiling tightens to 15
- **Centralised Input Bounds: Emailmaxlength and Passwordres:** Centralised input bounds: `EmailMaxLength` and `PasswordResetTokenMaxLength` helpers beside the
  existing `PasswordMaxLength` (config-driven `max` rules with Title Case copy on login, register,
  recovery, and admin creation; reset tokens bound to the broker's 64 characters); `Password::defaults()`
  now caps length so overlong input never reaches Argon2id, and the breach verifier runs with a
  3-second timeout so a slow HIBP endpoint cannot stall workers
- **EnvExampleParityTest:** `EnvExampleParityTest` - asserts application config keys are documented in `.env.example` and that
  `.env.ci` mirrors the same section banners and key contract

### Changed

- **Environment File Structure:** `.env.example` and `.env.ci` restructured to the `create-env-file` skill: purpose headers, canonical
  section order (GeoIP and product features before Frontend; security edge last), `(required)` /
  `(optional)` markers, unset-fallback comments on every commented key, and a full-mirror CI contract
- **Local Environment Gitignore:** `.gitignore` - `.env.local` and `.env.*.local` patterns so personal overlay files cannot be committed

## [1.9.0] - 2026-09-06

### Added

- **System Status Endpoint:** `GET /api/status` - public System Status page: current state and daily uptime history per monitored component (`database`, `cache`, `queue`), with worst-reading `overall_status` rollup and a `days` window query param (1-90, default 90); rate limited per IP via a dedicated `api-status` limiter (`API_STATUS_RATE_LIMIT_PER_MINUTE`, default 30)
- **System Health Checks:** `system_health_checks` table, `SystemHealthCheck` model and factory, and the `health:record` console command (scheduled every five minutes except in `local` and `testing`, `withoutOverlapping()`) - runs every tagged check and persists one row per component per run with a shared `checked_at` instant
- **System Health Subsystem: Systemhealthcheck Contract, Sys:** System Health subsystem: `SystemHealthCheck` contract, `SystemHealthStatus` enum, `SystemHealthCheckResult` DTO, `SystemHealthCheckRegistry` (container-tagged checks), `SystemHealthServiceProvider`, `DatabaseHealthCheck` (`select 1` with a 500 ms degraded threshold), `CacheHealthCheck` (throwaway-key round trip), `QueueHealthCheck` (sync reports Up; otherwise resolves the connection without dispatching a job), `SystemHealthHistoryQuery` (all aggregation in SQL), `ShowSystemStatusRequest`, and `SystemStatusController` - plus unit and feature tests and the `/status` OpenAPI path, schemas, and example
- **Password Recovery Endpoints:** `POST /api/auth/forgot-password` and `POST /api/auth/reset-password` - broker-based account recovery with enumeration-neutral responses and a shared `api-auth-password` rate limiter (composite email+IP plus per-IP ceiling)
- **ResetUserPasswordAction:** `ResetUserPasswordAction` - full credential rotation on reset: Personal Access Tokens revoked, web sessions stamped revoked with post-commit payload destruction, remember token rotated, `session_version` bumped
- **ResetPasswordNotification:** `ResetPasswordNotification` (queued, config-driven SPA destination via `API_PASSWORD_RESET_URL`) and `PasswordChangedNotification` (queued security alert with parsed request details), plus the `PasswordChangeSource` enum
- **Password Reset Requested:** `Password Reset Requested` and `Password Reset` audit events, a `sendPasswordResetNotification()` override on the User model, and new forgot/reset feature and notification unit tests
- **Self-service Password Hardening (`PATCH /api/me/password:** Self-service password hardening (`PATCH /api/me/password`): new password now enforces the shared `Password::defaults()` policy with `PasswordMaxLength` and the new `api.password_max_length` config, rejects a password identical to the current one, and dispatches the `PasswordChangedNotification` security alert (`PasswordChangeSource::SelfService`) after a successful change
- **Pluggable User-agent Parser (`config/useragent.php`, Bas:** Pluggable user-agent parser (`config/useragent.php`, `basic` and `null` drivers) - web sessions registered without an explicit `device_name` now derive one from the parsed user agent
- **Fail-closed Session Revocation: Invalidatestoredsessiona:** Fail-closed session revocation: `InvalidateStoredSessionAction` returns the store's destroy result, logs a truncated SHA-256 fingerprint instead of the raw session ID, and `failClosed()` bumps `session_version` so a store failure still recalls every cookie; adopted by the password-change, logout, surgical-revoke, and password-reset flows
- **Session Activity Tracking: Touchwebsessionactivityaction:** Session activity tracking: `TouchWebSessionActivityAction` and the `session.touch` middleware refresh `last_activity_at` on cookie sessions at most every five minutes; `session.version` exposes a shared `SESSION_KEY` constant
- **Password Recovery Endpoints:** `POST /api/auth/forgot-password` and reset flow added to `scripts/pen-test-auth.sh` (enumeration, rate limit, token binding, replay, credential rotation, audit) plus a session-activity probe, `docs/testing.md`, and the testing-doc link in `README.md`
- **Config/hashing.php:** `config/hashing.php` - Argon2id as the default password hash driver with env-driven work factors and `HASH_REHASH_ON_LOGIN`
- **Rehash-on-login in Authenticateuseraction:** Rehash-on-login in `AuthenticateUserAction` - transparently upgrades stale Argon2id hashes after work-factor bumps
- **RehashOnLoginTest:** `RehashOnLoginTest` - covers stale-hash upgrade, suspended-user guard (no rehash), and unchanged current hashes
- **Geoip Subsystem: the Geoiplocator Contract, the Geoiploc:** GeoIP subsystem: the `GeoIpLocator` contract, the `GeoIpLocation` DTO, the `GeoIpDatabase` singleton (bind in `AppServiceProvider::register()`), and the fail-open `MaxMindGeoIpLocator` (private/reserved-range skip outside `local`, `geoip.local_fallback_ip` lookup in `local`, city capped at 255 characters, uppercased ISO 3166-1 alpha-2 country)
- **Geoip:update:** `geoip:update` command - downloads GeoLite2-City with a 120 second timeout, validates the extracted MMDB by opening it with a `GeoIp2\Database\Reader` before touching the destination, and swaps it in with an atomic `rename()` on the destination filesystem; temp-dir cleanup and credential guards kept, no Octane runtime hint (this starter runs PHP-FPM/Sail)
- **Weekly Geoip:Update Schedule in Routes/Console.Php:** Weekly `geoip:update` schedule in `routes/console.php` - Sundays 03:15 UTC for `staging` and `production` only, `withoutOverlapping()`, skipped entirely when MaxMind credentials are not configured
- **Location City:** `location_city` and `location_country` columns on `web_sessions` and `auth_audit_logs` - written once per session registration (never on the activity heartbeat) and by the queued auth audit listener from the event IP, exposed on `WebSessionResource` behind the same `sessions.list-all` telemetry gate as `ip_address`/`user_agent` and as plain fields on `AuthAuditLogResource`, with sparse fieldset allow-lists, OpenAPI schemas, examples, and a `GeoIP` section in `.env.example`
- **Full Data Retention Policy: No Scheduled Pruning:** Full data retention policy: no scheduled pruning; every table (including `system_health_checks` and `auth_audit_logs`) retains its rows indefinitely by deliberate compliance decision, documented in `routes/console.php`
- **Health Probe:** `GET /health` request class (`ShowHealthRequest`) so the probe controller follows the every-controller-takes-a-FormRequest convention


### Fixed

- **Scalar Docs Embed Fix:** `/api/docs` rendered permanent loading skeletons with an empty sidebar: the unpinned `Scalar.createApiReference()` CDN embed never hydrated its Introduction section. Replaced with the attribute-based embed pinned to `@scalar/api-reference@1.64.1`, and the docs CSP now allows Scalar's font origin (`fonts.scalar.com`)

- **Duplicate Event Listener Registration:** Queued event listeners registered twice: event discovery (`app/Listeners`) and the manual `Event::listen()` calls in [AppServiceProvider](app/Providers/AppServiceProvider.php) both registered `RecordAuthAuditLog` and `SendTwoFactorCodeNotification`, so every audit row was written twice and every two-factor e-mail was queued twice. Manual registrations removed; discovery is the single source of registration

### Changed

- **Adversarial Security Hardening:** Security hardening from a full adversarial audit (banking-grade review of auth, recovery, platform, and data layers): Sanctum token abilities are now enforced at request time (`User::can()` composes Spatie permissions with the token's abilities, so a client scoped to `['roles.list']` can no longer reach `users.list-all` data); deactivating an API client revokes its live bearer tokens; the demo API client is only seeded in `local`/`testing` and re-seeding no longer resurrects a revoked client; session-bound two-factor pendings expire with the configured TTL instead of living as long as the session; failed password-reset attempts are recorded in the audit log (`Password Reset Failed`); every response ships `Cache-Control: no-store, private`; `AuthAuditLogPolicy` enforces the `audit-logs.list` permission instead of a bare role check; user email sort/search are gated behind `users.view-email` (enumeration oracle closed); trusted-proxy support via `TRUSTED_PROXIES` (default trusts nothing); `/health` and `/api/status` throttled per IP; boot-time guard rejects reflective CORS with credentials; token ability arrays capped at 50; docs CSP tightened (no `unsafe-inline` scripts) with SRI pinning of the Scalar bundle; `npm audit` advisory (nanoid) resolved
- **Environment File Dividers:** All env files (`.env`, `.env.example`, `.env.ci`, `.env.testing.local`) restructured with pipe-style section dividers and section bodies; removed unused variables (`MEMCACHED_HOST`, the unused `AWS_*` S3 block, and `BROADCAST_CONNECTION` - nothing in the codebase broadcasts)
- **API Route Grouping:** `routes/api.php` regrouped into labelled sections (Public Authentication / Authenticated API); `scripts/pen-test-auth.sh` section headlines now Title Case
- **.env.example:** `.env.example`, `.env.ci`, and `phpunit.xml` - aligned low-cost Argon2id settings for tests and CI
- **Login Timing Hash Documentation:** Login timing-normalisation docs and config comments - dummy hash uses the configured driver, not bcrypt-only wording
- **Documentation Comment Sweep:** Documentation and comment sweep: full PHPDoc on every factory and support method (summary, `@param`, `@return`), block comments replace stacked `//` paragraphs, `ID` title-cased in prose, stale porting references removed, route divider bodies restored to Title Case, and `pint.json` keeps explicit `@return` tags (`no_superfluous_phpdoc_tags: false`)


## [1.8.1] - 2026-08-28

### Added

- **Semgrep SAST CI Job with Laravel Security Rules:** Semgrep SAST CI job with Laravel security rules (`scripts/semgrep.sh`)

### Changed

- **FormRequest Section Dividers:** HTTP request classes use semantic section dividers instead of generic Public/Protected blocks
- **Session Cookie Defaults:** Session cookie defaults: `domain` defaults to `null`, `secure` defaults to `true` (`SESSION_SECURE_COOKIE=false` in `.env.example` and `.env.ci` for local HTTP)
- **Dependabot Cooldown:** Dependabot cooldown (`default-days: 7`) and npm `min-release-age=7` for Semgrep supply-chain checks
- **Development Dependency Bumps:** Dev dependencies: laravel/framework 13.29.0, phpunit 13.3.2, mockery 1.6.15, phpstan 2.2.9, Symfony 8.1.5, and related transitive bumps

## [1.8.0] - 2026-08-19

### Added

- **Web Sessions API:** `GET /api/sessions`, `DELETE /api/sessions/{web_session}`, and `DELETE /api/sessions/current` - cookie-bound web session registry with admin `sessions.list-all` / `sessions.revoke-any` support
- **Web Sessions:** `web_sessions` table, `WebSession` model, and `RegisterWebSessionAction` - sessions registered at the privilege boundary (login, two-factor completion, remember-me restore)
- **Sessions.list-own:** `sessions.list-own`, `sessions.list-all`, `sessions.revoke-own`, and `sessions.revoke-any` permissions (permission catalog count is now 25)
- **Session Telemetry Gating in Websessionresource:** Session telemetry gating in `WebSessionResource` - `user_id` and cross-user `ip_address` / `user_agent` require `sessions.list-all`
- **Adversarial Pen-test Coverage for the Sessions Surface:** Adversarial pen-test coverage for the sessions surface in `scripts/pen-test-auth.sh`

### Changed

- **Auth Route Prefix:** Auth routes moved under `/api/auth/*` (`/login`, `/register`, `/two-factor/*`, `/login/remember`); global logout remains `POST /api/logout`
- **Logout Web Session Revocation:** Global logout revokes every `web_sessions` registry row via `RevokeAllWebSessionsForUserAction`
- **CLI Diagnostic Copy:** CLI and CI diagnostic copy uses Title Case headlines per project conventions
- **Development Dependency Bumps:** Dev dependencies: phpunit 13.3.1, laravel/pao 1.1.4, mockery 1.6.13, laravel/pint 1.30.5, laravel/sail 1.67.0

### Fixed

- **Scripts/verify-openapi-examples.sh:** `scripts/verify-openapi-examples.sh` restores `${BASE}` on auth path curls so the OpenAPI examples job does not abort on malformed URLs
- **Scripts/pen-test-auth.sh:** `scripts/pen-test-auth.sh` falls back to seeded registry rows when cookie login cannot complete for MFA-enrolled accounts

## [1.7.0] - 2026-08-17

### Added

- **System Status Endpoint:** `GET /api/auth/two-factor/status` - poll pending two-factor challenge expiry for session-bound and stateless clients (`GetTwoFactorStatusAction`)
- **Separate Rate Limiters for Two-factor Send, Verify:** Separate rate limiters for two-factor send, verify, and status polling (`API_TWO_FACTOR_SEND_*`, `API_TWO_FACTOR_VERIFY_*`, `API_TWO_FACTOR_STATUS_RATE_LIMIT_PER_MINUTE`)
- **Per-route Content Security Policy:** Per-route Content Security Policy - strict default for API responses, relaxed Scalar/jsDelivr policy for `GET /api/docs`; CSP omitted in `local` while Vite hot-reload is active

### Changed

- **Suspend Credential Revocation:** Suspending a User now revokes every Sanctum token, bumps `session_version`, and clears sessions inside the same database transaction
- **Stale Session Version Logout:** Stale `session_version` on cookie sessions logs the User out, invalidates the session, and regenerates the CSRF token
- **Session Version Stamping:** `session_version` is stamped when authentication completes rather than auto-stamped by middleware on first request
- **Bearer Session Version Skip:** Bearer-authenticated requests skip the session-version gate even when an `Origin` header binds a session cookie
- **PHPUnit CSP Enforcement:** PHPUnit enforces CSP in the testing environment (`SECURITY_CSP_ENFORCE=true`)

### Fixed

- **Auth Audit User-Agent Cap:** User-Agent values in auth audit logs are capped at 1024 characters in `RecordAuthAuditAction` (single normalisation path for direct and queued writes)
- **Action Test Namespace:** DB-touching Action and listener tests moved from `tests/Unit/` to `tests/Feature/` to match the no-database unit-test contract

## [1.6.0] - 2026-08-17

### Added

- **Email Two-factor Authentication:** Email two-factor authentication - `POST /api/auth/two-factor/send` and `POST /api/auth/two-factor/verify` complete sign-in after valid credentials when `mfa_method` is set; opaque `two_factor_token` supports stateless clients
- **Users API:** `POST /api/users` - admin user creation with role and optional `team_id` (`users.create`); new accounts auto-enrol in email MFA
- **Clients API:** `PATCH /api/clients/{client}` - update API client name, abilities, and active status (`api-clients.update`)
- **Users.create:** `users.create` and `api-clients.update` permissions; permission catalog count is now 21
- **MFA Method:** `mfa_method` column on users (migration); `MfaMethod` enum; audit events for two-factor issued, verified, and failed
- **FinaliseAuthenticatedSessionAction:** `FinaliseAuthenticatedSessionAction` - shared remember-me, token issuance, and login audit for password and two-factor completion flows
- **Queued Twofactorchallengeissued Event and Sendtwofactorc:** Queued `TwoFactorChallengeIssued` event and `SendTwoFactorCodeNotification` listener for off-request email delivery
- **Configurable Two-factor TTLs and Attempt Limits (`Api Tw:** Configurable two-factor TTLs and attempt limits (`API_TWO_FACTOR_CODE_TTL_SECONDS`, `API_TWO_FACTOR_PENDING_TTL_SECONDS`, `API_TWO_FACTOR_MAX_ATTEMPTS`)

### Changed

- **Register and Login Two-Factor Gate:** `POST /api/auth/register` and MFA-enrolled `POST /api/auth/login` return `two_factor_required` and `two_factor_token` instead of an immediate bearer token - complete send/verify before a session is issued
- **Email Lowercase Normalisation:** Email addresses are normalised to lowercase on register, login, admin user creation, and API client service-user emails
- **Two-Factor Documentation:** OpenAPI, README, and `docs/permissions.md` updated for two-factor flow, user creation, and client PATCH

## [1.5.0] - 2026-08-16

### Added

- **Profile Self-Service API:** `PATCH /api/me` - self-service profile update (`name` only; `email`, `password`, and `team_id` are prohibited)
- **Profile Self-Service API:** `PATCH /api/me/password` - self-service password change (requires the current password and rotates the User's sessions)
- **Teams API:** `GET /api/teams` and `GET /api/teams/{team}` - read-only Team index and show with the standard sort, `fields[teams]`, and `filter[search]` contract (`teams.list` on Admin and Manager)
- **Users API:** `POST /api/users/{user}/suspend` and `POST /api/users/{user}/unsuspend` - admin account suspension (`users.suspend`); suspended identities are turned away on every authenticated route
- **Health Probe:** `GET /health` - public uptime probe (no auth) reporting database reachability and the application version

### Changed

- **Application Version Source:** Application version now derives from the `version` field in `composer.json` via `config('app.version')` (override with `APP_VERSION`); `/health` reports it
- **CI Version Sync Gate:** CI adds a `composer verify:version` gate - fails when `docs/openapi.yaml` drifts from `composer.json`

### Fixed

- **OAuth Token Expiration:** OAuth client-credentials tokens honour their own expiration instead of falling back to the user Personal Access Token lifetime

## [1.4.0] - 2026-08-16

### Added

- **Public Post /Api/Auth/Login and Post /Api/Auth/Register:** Public `POST /api/auth/login` and `POST /api/auth/register` endpoints - password auth with Sanctum
  bearer tokens, generic invalid-credential responses, and `api-auth` rate limiting
  (10 requests / minute per IP and email)
- **Global Logout Endpoint:** `POST /api/logout` - revokes every Sanctum token, clears remember-me state, deletes all
  server-side session rows for the User, and invalidates the current request session when
  present
- **Auth Audit Logs:** `auth_audit_logs` table and `RecordAuthAuditAction` - audit trail for login, failed
  login, logout, registration, and remember-me session restoration
- **Remember-me on Post /Api/Auth/Login (`remember: True`):** Remember-me on `POST /api/auth/login` (`remember: true`) - extended PAT lifetime, rotated
  `remember_token`, web-guard remember cookie; `POST /api/auth/login/remember` for SPA re-auth
- **Users API:** `POST /api/users/logout` - admin-only force-logout by User ID; revokes every Sanctum
  token, clears remember-me state, and deletes all server-side session rows for each target
- **OAuth2 Client-credentials Flow:** OAuth2 client-credentials flow - `POST /api/oauth/token` exchanges `client_id` and
  `client_secret` for scoped Sanctum tokens; `api_clients` table, `Service` role, service
  User accounts (`is_service_account`), admin `GET|POST|DELETE /api/clients`, `GET /api/clients/{client}`,
  and demo seeded
  client `demo-integration-client`
- **Auth Audit Logs API:** `GET /api/audit-logs` and `GET /api/audit-logs/{auth_audit_log}` - admin read-only
  auth audit log index and show with search, event, user, and API client filters
  plus `include=user` on list and show
- **Permissions Catalog API:** `GET /api/permissions` - read-only Spatie permission catalog for token and API
  client ability pickers (`permissions.list` on Admin, Manager, and User)
- **Profile Self-Service API:** `GET /api/me` - caller profile without `users.list`
- **AuthenticatedUserResource:** `AuthenticatedUserResource` for login and registration responses (always includes email
  for the session owner)
- **Account Suspension (`suspended_at`) with Active.Account:** Account suspension (`suspended_at`) with `active.account` middleware and adversarial
  `scripts/pen-test-auth.sh` coverage

### Changed

- **OpenAPI Spec Sync:** OpenAPI spec synced with current routes (`GET /permissions`, audit log show,
  client show), suspension behaviour, and live response examples
- **Suspended Account Login:** Suspended accounts are rejected at login and OAuth client-credentials exchange
  with the same generic `Invalid Credentials` message as wrong passwords - they
  no longer receive a bearer token that only fails on the next API call
- **Registration Enumeration Guard:** Registration duplicate-email validation returns the generic `Invalid Credentials` message
  (same as login) so callers cannot enumerate accounts via `/register`
- **Login Timing Normalisation:** Login credential checks run a dummy password hash when the email is unknown to reduce
  timing side-channels that reveal account existence
- **Remember-Me Recaller Cookie:** Stateful remember-me login refreshes the User before `Auth::login()` so the remember
  recaller cookie is queued on the response

## [1.3.1] - 2026-08-12

### Added

- **Release Runbook:** Release runbook in `docs/releasing.md` - changelog, quality gates, tag, and GitHub publish steps

### Changed

- **README Visual Polish:** README visual polish - centred header badges, Mermaid pipeline diagram, GitHub callouts, expanded testing and quality-gate notes

## [1.3.0] - 2026-08-12

### Changed

- **Index Helper Rename:** Rename index helpers from `List*` to `Index*`: `IndexSort`, `IndexSortQuery`, `IndexFieldsQuery`, `IndexSortParser`; FormRequest accessor `indexSort()` (was `listSort()`)
- **Search Term Parser Extraction:** Extract `SearchTermParser` for `filter[search]` normalisation; remove trait harness unit tests and `tests/Support/` classes - coverage via Support unit tests and feature/resource tests on real hosts
- **Dependency Bumps:** Bump `spatie/laravel-permission` to ^8.3, `phpunit/phpunit` to ^13.3, and `laravel/telescope` to ^5.22.1

## [1.2.1] - 2026-08-11

### Changed

- **GitHub Actions Bump:** Bump GitHub Actions to v7; add Dependabot for Actions and sync `.env.ci` with API rate-limit and CORS settings
- **CI Workflow Hardening:** Harden CI workflow concurrency and retrigger pipeline after GitHub Actions outage
- **Dependabot Composer Updates:** Add weekly Dependabot version updates for Composer (`open-pull-requests-limit: 10`)

### Fixed

- **Changelog Compare Link:** Fix `v1.2.0` changelog compare link footer

## [1.2.0] - 2026-08-06

### Added

- **IndexSortParser:** `IndexSortParser` and `AllowList` Support helpers for query-param parsing and allow-list comparison
- **Unit Tests for Indexsortparser and Allowlist with:** Unit tests for `IndexSortParser` and `AllowList` with adversarial `#[DataProvider]` coverage
- **CORS Configuration (`config/cors.php`) and Apicorstest F:** CORS configuration (`config/cors.php`) and `ApiCorsTest` feature coverage for browser clients
- **Personal Access Token Expiration via Api Token Expiratio:** Personal Access Token expiration via `API_TOKEN_EXPIRATION_DAYS` (default 90), synced to Sanctum config
- **Feature Tests for Expired Sanctum Tokens and:** Feature tests for expired Sanctum tokens and token `expires_at` on creation

### Changed

- **FormRequest Parse Traits Delegate Grammar to Support:** FormRequest parse traits delegate grammar to Support classes; traits retain HTTP wiring only
- **Removed Redundant FormRequest Harness Unit Tests:** Removed redundant FormRequest harness unit tests - fields, include, and sort wiring covered by feature tests
- **OpenAPI Tokensindexsuccess Example Uses Nullable Last Us:** OpenAPI `TokensIndexSuccess` example uses nullable `last_used_at` for newly issued tokens
- **OpenAPI Verify Script Pre-creates Index Tokens and:** OpenAPI verify script pre-creates index tokens and requests `sort=-id` for stable envelopes

### Fixed

- **PHPStan Test Types:** PHPStan types in `StoreTokenControllerTest` for token expiration assertions

## [1.1.0] - 2026-08-06

### Added

- **Unit Tests for API Resources (`UserResource`, Roleresour:** Unit tests for API Resources (`UserResource`, `RoleResource`, `PermissionResource`, `TeamResource`, `PersonalAccessTokenResource`) and `SerialisesSparseAttributes`
- **Unit Tests for FormRequest Concerns (`ParsesFieldsQueryP:** Unit tests for FormRequest concerns (`ParsesFieldsQueryParam`, `ParsesIncludeQueryParam`, `ParsesSearchQueryParam`, `SanitisesPlainTextAttributes`, `ValidatesTokenPayload`)
- **Unit Tests for Support Helpers (`CommaSeparatedList`, Ap:** Unit tests for Support helpers (`CommaSeparatedList`, `ApiExceptionRenderer`, `ApiDateTime`, `AllowListValidation`, `ApiResponse`, `LikePattern`, `PlainText`, `QualifiedColumn`)
- **Feature Tests for Sanctum Authentication, API Token:** Feature tests for Sanctum authentication, API token rate limiting, and security probes (SQL injection, mass assignment, boundary inputs)
- **Feature Tests for Userpolicy Edge Cases and:** Feature tests for `UserPolicy` edge cases and expanded controller coverage
- **CI Job Verifying OpenAPI Component Examples Against:** CI job verifying OpenAPI component examples against live API responses (`composer verify:openapi`)
- **Fields[roles]:** `fields[roles]` sparse-fieldset validation on `UserIndexRequest` and matching invalid-query coverage

### Changed

- **FormRequest Conventions:** FormRequest concerns aligned with `ApiFormRequest` base and ai-rules conventions (`ParsesSearchQueryParam` `@mixin`, dash-underline test groups, PHPDoc on every `#[Test]`)
- **Test Suite Layout:** reorganised with `#[CoversTrait]` removed from feature tests; concern and resource tests use dedicated unit namespaces
- **Scripts/verify-openapi-examples.sh:** `scripts/verify-openapi-examples.sh` supports CI via `ARTISAN_CMD` and `OPENAPI_VERIFY_BASE` environment variables

## [1.0.0] - 2026-08-06

### Added

- **Laravel API Skeleton:** Laravel 13 API skeleton with Sanctum bearer authentication and Spatie roles and permissions
- **Query-Driven Resources:** Users, Roles, and Tokens resources with a consistent query-driven index pattern
- **OpenAPI and Docs:** OpenAPI specification, permissions reference, and performance notes
- **CI Quality Gates:** CI quality gates: Pint, Larastan level 10, PHPUnit with 90% line-coverage gate, and `composer audit`
- **Laravel Sail Setup:** Laravel Sail setup with MySQL and Redis for local development
[Unreleased]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.16.1...HEAD
[2.0.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.16.1...v2.0.0
[1.16.1]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.16.0...v1.16.1
[1.16.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.15.4...v1.16.0
[1.15.4]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.15.3...v1.15.4
[1.15.3]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.15.2...v1.15.3
[1.15.2]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.15.1...v1.15.2
[1.15.1]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.15.0...v1.15.1
[1.15.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.14.3...v1.15.0
[1.14.3]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.14.2...v1.14.3
[1.14.2]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.14.1...v1.14.2
[1.14.1]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.14.0...v1.14.1
[1.14.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.13.0...v1.14.0
[1.13.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.12.0...v1.13.0
[1.12.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.11.0...v1.12.0
[1.11.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.10.0...v1.11.0
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
