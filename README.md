<div align="center">

# Laravel API Starter

**A production-ready Laravel 13 API with Sanctum auth, Spatie permissions, and a
query-driven resource pattern you can copy for every endpoint.**

[![CI](https://img.shields.io/github/actions/workflow/status/Aontaigh/laravel-api-skeleton/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Aontaigh/laravel-api-skeleton/actions/workflows/ci.yml)
[![Version](https://img.shields.io/github/v/tag/Aontaigh/laravel-api-skeleton?label=version&style=flat-square)](https://github.com/Aontaigh/laravel-api-skeleton/releases)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)

</div>

<p align="center">
  <img src="docs/images/banner.svg" alt="Laravel API Starter - Sanctum auth, Spatie permissions, query-driven resources" width="100%">
</p>

Ships with fully wired resources - **Users**, **Roles**, **Teams**, **API Tokens**, **API
Clients**, **Web Sessions**, and **Audit Logs** - each implementing the same index contract
(`sort`, `fields`, `include`, `filter`, pagination) where it applies.
Clone, run Sail, issue a token, open **[http://localhost/api/docs](http://localhost/api/docs)**
(Scalar try-it UI), or import [docs/openapi.yaml](docs/openapi.yaml) into Postman.

Patterns align with an internal shared conventions toolkit - invokable controllers,
FormRequest validation, query classes, API Resources, and server-side Policies. Every
convention below is demonstrated in this repo, so nothing here depends on that access.

## Table of Contents

- [🧭 About](#-about)
- [🚀 Quick Start](#-quick-start)
- [⚙ How the Query-Driven API Works](#-how-the-query-driven-api-works)
- [📚 Documentation](#-documentation)
- [🧱 Stack](#-stack)
- [📋 Requirements](#-requirements)
- [🔌 API](#-api)
- [🖥 API Reference](#-api-reference)
- [🛡 Security](#-security)
- [🏗 Architecture](#-architecture)
- [📁 File Structure](#-file-structure)
- [✅ Quality Gates](#-quality-gates)
- [🧪 Testing](#-testing)
- [🚫 What's Not Included](#whats-not-included)
- [📄 License](#-license)

## 🧭 About

This repo is a **starter template**, not a finished product. It demonstrates conventions
you can copy into greenfield APIs or port legacy endpoints toward over time.

**What you get:**

- 🧭 Paginated, filterable **user** index with team row scoping and permission-gated fields
- 👥 **Role** index and **team** management (create, rename, guarded delete) for management UIs
- 🔑 Self-service **profile** update, password change, and admin-issued **API Tokens** via Sanctum
- 🔐 Email **two-factor authentication** with stateless pending challenges and broker-based **password recovery** that rotates every credential
- 🖥️ Device **session registry** with per-device revocation, fail-closed store handling, and an append-only **Auth Audit Log**
- 🛡️ Admin **account suspension** (suspend / unsuspend), a public `/health` probe, and a public `/api/status` uptime page
- 📖 Hand-written [OpenAPI 3.1](docs/openapi.yaml) spec with hosted [Scalar](https://scalar.com) docs at `/api/docs` (local and production)
- 🐳 Dockerised local dev via [Laravel Sail](https://laravel.com/docs/sail)
- ✅ 90% line-coverage CI gate with parallel quality jobs

**Who it's for:** teams bootstrapping a JSON API, architects evaluating a consistent
resource layer, or agents mapping a predictable Laravel layout.

## 🚀 Quick Start

> [!IMPORTANT]
> The project requires **PHP ^8.5**. If your host PHP is older, use Sail for every
> command below (`./vendor/bin/sail …`). GitHub CI runs native PHP 8.5 - not Sail containers.

```bash
composer install
cp .env.example .env
php artisan key:generate

./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate:fresh --seed
```

Seeding creates one team ("Acme Corp") and one user per role. The full permission matrix
lives in [docs/permissions.md](docs/permissions.md).

| Email | Role |
| --- | --- |
| `admin@example.com` | Admin |
| `manager@example.com` | Manager |
| `test@example.com` | User |

All seeded accounts use the dev-only password `password`. Never seed accounts with this
password outside `local`.

Issue a bearer token via login, registration, or Tinker:

```bash
curl -s -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}' | jq -r '.data.plain_text_token'
```

Or with Tinker:

```bash
./vendor/bin/sail artisan tinker --execute="echo App\Models\User::where('email', 'admin@example.com')->first()->createToken('local')->plainTextToken;"
```

> [!TIP]
> Open [http://localhost/api/docs](http://localhost/api/docs), click **Authentication** in
> Scalar, and paste `Bearer {token}` - the fastest way to explore sort, fields, include,
> and filter on a live API. Details: [docs/api.md](docs/api.md#interactive-docs-scalar).

**Try it (curl)** - list users with team and role includes:

```bash
curl -s -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost/api/users?fields[users]=id,name&include=team,role&per_page=5" | jq
```

All routes below require `Authorization: Bearer {token}` unless noted.

### Authentication (Public)

```http
POST /api/auth/login           # {"email": "...", "password": "...", "remember": optional, "device_name": optional}
POST /api/auth/two-factor/send # {"channel": "email", "two_factor_token": optional}
GET  /api/auth/two-factor/status # ?two_factor_token=optional - poll pending challenge expiry
POST /api/auth/two-factor/verify # {"code": "123456", "device_name": optional, "two_factor_token": optional}
POST /api/auth/login/remember  # Stateful SPA re-auth via remember-me cookie or session
POST /api/auth/register        # {"name": "...", "email": "...", "password": "...", "password_confirmation": "..."}
POST /api/auth/forgot-password # {"email": "..."} - always a generic success; link only sent for existing accounts
POST /api/auth/reset-password  # {"token": "...", "email": "...", "password": "..."} - rotates every credential
GET  /api/auth/email/verify/{id}/{hash} # Temporary signed link e-mailed on register and resend; redirects to the SPA result page
POST /api/auth/email/resend    # Authenticated; queues a fresh link for an unverified account (generic response)
POST /api/oauth/token     # {"grant_type":"client_credentials","client_id":"...","client_secret":"..."}
POST /api/logout          # Bearer token required - revokes every token and server session
```

No prior token required for login, register, and client-credentials exchange. Login and register are
rate-limited per email+IP (`API_AUTH_RATE_LIMIT_PER_MINUTE`, default **5**) backed by a broad per-IP
ceiling (`API_AUTH_IP_CEILING_PER_MINUTE`, default **20**). Client-credentials exchange is rate-limited
per `client_id`+IP (`API_CLIENT_AUTH_RATE_LIMIT_PER_MINUTE`, default **5**) with the same per-IP ceiling
pattern. The per-IP ceiling is skipped in `local` so the dev suite never self-throttles. After seed,
use demo client `demo-integration-client` / `DemoClientSecret12`. Admins manage clients via
`GET|POST|PATCH|DELETE /api/clients` and `GET /api/clients/{client}`. Registration assigns the
default `User` role with `team_id` null, auto-enrols email two-factor authentication, and returns
`two_factor_required` plus an opaque `two_factor_token` - no bearer token until send/verify complete.
Invalid login credentials return a generic `Invalid Credentials` message on the `email` field. Users
with email MFA enrolled receive `two_factor_required: true` and `two_factor_token` after valid
credentials - complete `POST /api/auth/two-factor/send` then `POST /api/auth/two-factor/verify` on
the same session (or pass `two_factor_token` on stateless clients) before a bearer token is issued.

**E-Mail Verification:** registration queues a temporary signed link (`AUTH_VERIFICATION_EXPIRE`,
default **60** minutes) pointing at `API_EMAIL_VERIFICATION_URL`. An unverified account can still
log in, read `GET /me`, resend the link, and sign out - every other business route answers **403**
until the address is confirmed. Resend is keyed on the authenticated User ID + IP
(`API_EMAIL_VERIFICATION_RATE_LIMIT_PER_MINUTE`, default **3**) and both answers are generic.
Login, logout, registration, e-mail verification outcomes, failed logins, and remember-me restores
are recorded to `auth_audit_logs` - written by a
**queued listener** ([RecordAuthAuditLog](app/Listeners/RecordAuthAuditLog.php)) off the
request hot path, so a queue worker must be running in non-`sync` environments.

Set `remember: true` on login for industry-standard remember-me - extended Sanctum
token lifetime (`API_REMEMBER_TOKEN_EXPIRATION_DAYS`, default **365**), a rotated
`remember_token`, and a web-guard remember cookie for stateful SPAs. Call
`POST /api/auth/login/remember` to obtain a fresh bearer token without re-entering credentials.
The session ID is **regenerated at the privilege boundary** on both remember-me login and
remember-me restore, so a fixated pre-auth session ID can never survive authentication.

`POST /api/logout` requires a bearer token and ends the session everywhere: all Sanctum
tokens for the User are revoked, remember-me state is cleared, the User's `session_version`
is bumped, and every server-side session row for that User is deleted. The version bump is
the driver-agnostic part - the `session.version` middleware
([EnsureSessionVersionMatches](app/Http/Middleware/EnsureSessionVersionMatches.php)) turns
away any Web Session stamped with a superseded version on its next request, so "log out
everywhere" holds whether sessions live in the database, Redis, or files.

Unauthenticated account recovery follows the same rules. `POST /api/auth/forgot-password`
answers identically whether or not the address exists, and the broker silently throttles
repeat requests (60 seconds per address) without changing the response. The e-mailed link
points at the configured SPA page (`API_PASSWORD_RESET_URL`, falling back to the API host in
local development) and expires after 60 minutes. `POST /api/auth/reset-password` treats a
valid token as a full credential rotation: all Sanctum tokens are revoked, every Web Session
row is stamped revoked and its stored payload destroyed after commit, `session_version` is
bumped so stale cookies die, and the remember-me token is rotated. Unknown, expired,
mismatched, and replayed tokens all return the same generic 422. A queued password-changed
security alert is sent after a successful reset (source: `A Password Reset Link`).

**Source of Truth:** [LoginController](app/Http/Controllers/Auth/LoginController.php),
[RegisterController](app/Http/Controllers/Auth/RegisterController.php),
[LogoutController](app/Http/Controllers/Auth/LogoutController.php),
[RememberLoginController](app/Http/Controllers/Auth/RememberLoginController.php),
[ForgotPasswordController](app/Http/Controllers/Auth/ForgotPasswordController.php), and
[ResetPasswordController](app/Http/Controllers/Auth/ResetPasswordController.php).

## ⚙ How the Query-Driven API Works

Every list endpoint follows the same request pipeline. Allow-lists live in code, not
convention - unknown params return `422`.

```mermaid
flowchart LR
    A[HTTP Request<br/>query params] --> B[FormRequest<br/>Policy + parse]
    B --> C[Query Classes<br/>sort, fields, include, filter]
    C --> D[API Resource<br/>shape + gate fields]
    D --> E[ApiResponse<br/>data + pagination meta]
```

**Source of truth:** allow-lists in [`app/Queries/*/*QueryConstraints.php`](app/Queries/),
parse grammar in [`app/Support/`](app/Support/) (`IndexSortParser`, `SearchTermParser`,
`CommaSeparatedList`, `AllowList`), authorisation in [`app/Policies/`](app/Policies/),
machine-readable contract in [docs/openapi.yaml](docs/openapi.yaml).

## 📚 Documentation

| Doc | Purpose |
| --- | --- |
| [README.md](README.md) | 📌 Orientation, quick start, architecture summary |
| [/api/docs](http://localhost/api/docs) | Scalar interactive reference - try endpoints in the browser |
| [docs/openapi.yaml](docs/openapi.yaml) | 📄 OpenAPI 3.1 source file (also at `/api/openapi.yaml`) |
| [docs/api.md](docs/api.md) | 🖥️ Scalar setup, preview, import, and sync checklist |
| [docs/permissions.md](docs/permissions.md) | 🔐 Permission strings, role matrix, Policy links |
| [docs/performance.md](docs/performance.md) | ⚡ Pagination and search trade-offs at scale |
| [docs/releasing.md](docs/releasing.md) | 🏷️ Cutting a release - changelog, gates, tag, GitHub publish |
| [docs/testing.md](docs/testing.md) | 🧪 Quality gates, test suites, coverage floor, pen test |
| [docs/security-audit.md](docs/security-audit.md) | 🏦 Adversarial security audit: findings and dispositions |

## 🧱 Stack

| Package | Purpose | Docs |
| --- | --- | --- |
| [Laravel 13](https://laravel.com/docs) | API framework | [laravel.com/docs](https://laravel.com/docs) |
| [Laravel Sanctum](https://laravel.com/docs/sanctum) | Bearer token authentication | [Sanctum](https://laravel.com/docs/sanctum) |
| [Spatie Laravel Permission](https://github.com/spatie/laravel-permission) | Roles (`Admin`, `Manager`, `User`, `Service`) and fine-grained permissions | [Package docs](https://spatie.be/docs/laravel-permission) |
| Larastan + Pint | Static analysis (level 9) and formatting | [Larastan](https://github.com/larastan/larastan) |
| PHPUnit | Unit and feature tests with a 90% line-coverage gate | [PHPUnit](https://phpunit.de) |
| [Laravel Sail](https://laravel.com/docs/sail) | Dockerised local development | [Sail](https://laravel.com/docs/sail) |
| [Laravel Telescope](https://laravel.com/docs/telescope) | Request debugging (admin-only gate, local only) | [Telescope](https://laravel.com/docs/telescope) |
| [Scalar](https://scalar.com) | Hosted interactive API reference at `/api/docs` | [Scalar docs](https://scalar.com/products/api-references/integrations/html-js) |

## 📋 Requirements

| Dependency | Version |
| --- | --- |
| PHP | ^8.5 |
| Laravel | ^13.8 |
| Docker (for Sail) | any recent version |

## 🔌 API

Every list endpoint shares this query contract:

| Param | Purpose |
| --- | --- |
| `sort` | Whitelisted column; prefix `-` for descending (default `id` ascending) |
| `fields[{resource}]` | Sparse fieldset - only requested columns are selected and returned |
| `include` | Whitelisted eager loads for nested relations |
| `filter[{key}]` | Resource-specific filters (e.g. `filter[search]` - trimmed via `SearchTermParser`) |
| `page`, `per_page` | Pagination |

> [!WARNING]
> `per_page` is capped at **100**. Larger values return `422`.

Unknown sort columns, filter keys, field names, or include relations return `422`.
Full allow-lists: [docs/openapi.yaml](docs/openapi.yaml) and `*QueryConstraints` classes.

### Users

```http
GET /api/users?filter[search]=acme&fields[users]=id,name,email&include=team,role&sort=-created_at&page=1&per_page=25
```

**Row Scoping:** Managers and Users see their own team only; Admins see all teams
(`users.list-all`). Details: [docs/permissions.md](docs/permissions.md#userslist-vs-userslist-all).

**Field Visibility:** `email` is omitted unless the viewer holds `users.view-email` -
enforced in [UserResource](app/Http/Resources/UserResource.php), not only the query.

**Profile:** `GET /api/me` returns the authenticated User's own record - no `users.list`
required. Supports the same `fields`/`include` allow-lists as show. Service accounts
receive `403`. The authenticated User may also update their own `name` via
`PATCH /api/me` and change their password via `PATCH /api/me/password`.

**Admin Creation:** `POST /api/users` creates an account with caller-specified role,
optional `team_id`, and optional canonical E.164 `phone` (`users.create`, Admin only).
Email is normalised to lowercase;
new accounts are auto-enrolled in email MFA; no bearer token is returned.

**Source of Truth:** [UserQueryConstraints](app/Queries/Users/UserQueryConstraints.php),
[UserPolicy](app/Policies/UserPolicy.php), [MeShowController](app/Http/Controllers/Users/MeShowController.php),
[StoreUserController](app/Http/Controllers/Users/StoreUserController.php).

| Method | Path | Notes |
| --- | --- | --- |
| `GET` | `/api/me` | Current user's profile - any token holder except service accounts |
| `PATCH` | `/api/me` | Update own `name`; `email`/`password`/`team_id` prohibited |
| `PATCH` | `/api/me/password` | Change own password (requires current password) |
| `GET` | `/api/users` | Paginated index |
| `POST` | `/api/users` | Create account (`users.create`); optional `role`, `team_id`, and E.164 `phone` |
| `GET` | `/api/users/{user}` | Show - same `fields`/`include` as index |
| `PATCH` | `/api/users/{user}` | Update `name`/`phone`; Admins may reassign `team_id` (`users.reassign-team`) and `role` (`users.assign-role`, never self/service, never last Admin) |
| `DELETE` | `/api/users/{user}` | Soft-delete; cannot delete own account |
| `POST` | `/api/users/logout` | Admin force-logout by IDs (`users.force-logout`) |
| `POST` | `/api/users/{user}/tokens` | Admin token issuance (`tokens.create-for-user`) |
| `POST` | `/api/users/{user}/suspend` | Admin suspend (`users.suspend`) |
| `POST` | `/api/users/{user}/unsuspend` | Admin unsuspend (`users.suspend`) |

### Teams

```http
GET   /api/teams?filter[search]=engineering&fields[teams]=id,name&sort=name
POST  /api/teams                        # {"name": "..."} (teams.create, Admin only)
GET   /api/teams/{team}?fields[teams]=id,name
PATCH /api/teams/{team}                 # {"name": "..."} (teams.update, Admin only)
DELETE /api/teams/{team}                # teams.delete, Admin only; 422 while Users are assigned
```

Listing and show require `teams.list` (Admin and Manager) with the standard
sort, `fields[teams]` (`id`, `name`), and `filter[search]` contract - no includes.
Creation, update, and deletion are Admin-only. Deleting a Team that still has
assigned Users answers `422` - reassign or remove the members first, so nobody is
silently un-scoped to `team_id` null.

**Source of Truth:** [TeamQueryConstraints](app/Queries/Teams/TeamQueryConstraints.php),
[TeamPolicy](app/Policies/TeamPolicy.php).

### Roles

```http
GET /api/roles?filter[search]=admin&fields[roles]=id,name&include=permissions&sort=name
GET /api/roles/{role}?fields[roles]=id,name&include=permissions
```

Requires `roles.list` (Admin and Manager). Scoped to the `web` guard Spatie stores on role rows.

**Source of Truth:** [RoleQueryConstraints](app/Queries/Roles/RoleQueryConstraints.php),
[RolePolicy](app/Policies/RolePolicy.php).

### Permissions

```http
GET /api/permissions?filter[search]=tokens&fields[permissions]=id,name&sort=name
```

Requires `permissions.list` (Admin, Manager, and User). Scoped to the `web` guard.
Powers Token and API Client ability pickers - the same catalog
[PermissionAbilityCatalog](app/Services/Permissions/PermissionAbilityCatalog.php)
validates on create.

**Source of Truth:** [PermissionQueryConstraints](app/Queries/Permissions/PermissionQueryConstraints.php),
[PermissionPolicy](app/Policies/PermissionPolicy.php).

### API Clients

```http
GET    /api/clients
POST   /api/clients                       # {"name": "...", "abilities": ["users.list"]}
GET    /api/clients/{client}
PATCH  /api/clients/{client}              # update name, abilities, and/or is_active
DELETE /api/clients/{client}
```

Requires `api-clients.list`, `api-clients.create`, `api-clients.update`, and
`api-clients.delete` respectively (Admin only). The plaintext `client_secret` is
returned once on `POST`. Setting `is_active` to `false` blocks future
client-credentials exchange; existing bearer tokens are not revoked.

**Source of Truth:** [ApiClientQueryConstraints](app/Queries/ApiClients/ApiClientQueryConstraints.php),
[ApiClientPolicy](app/Policies/ApiClientPolicy.php).

### Tokens

```http
GET    /api/tokens
POST   /api/tokens                       # {"name": "...", "abilities": ["*"]}
DELETE /api/tokens/{token}
POST   /api/users/{user}/tokens          # Admin only
```

`GET /api/tokens` lists only the caller's tokens. Abilities default to `['*']` and are
validated against registered Spatie permissions via
[PermissionAbilityCatalog](app/Services/Permissions/PermissionAbilityCatalog.php). The plaintext
token is returned once on `POST` and never stored. New tokens expire after
`API_TOKEN_EXPIRATION_DAYS` (default **90**); set to `0` to disable expiration locally.

**Source of Truth:** [TokenQueryConstraints](app/Queries/Tokens/TokenQueryConstraints.php),
[PersonalAccessTokenPolicy](app/Policies/PersonalAccessTokenPolicy.php).

### Auth Audit Logs

```http
GET /api/audit-logs
GET /api/audit-logs/{auth_audit_log}
```

Admin-only read-only index of rows in `auth_audit_logs`. Requires the `Admin`
role (and `audit-logs.list`). Supports `filter[search]` (email),
`filter[event]`, `filter[user_id]`, `filter[api_client_id]`, sparse `fields[auth_audit_logs]`,
`include=user`, and the standard sort and pagination params.

Each row also carries the resolved `location_city` and `location_country` for the recorded
IP (MaxMind GeoLite2 via `geoip:update`; lookups fail open when the database is absent).

**Source of Truth:** [AuthAuditLogQueryConstraints](app/Queries/AuthAuditLogs/AuthAuditLogQueryConstraints.php),
[AuthAuditLogPolicy](app/Policies/AuthAuditLogPolicy.php).

### Health

```http
GET /health
```

Public uptime probe - no auth, no throttling. Served at the **root** (not under
`/api`). Returns the application version and whether the database answers `select 1`;
returns `503` when the database is unreachable.

**Source of Truth:** [ShowHealthController](app/Http/Controllers/Api/ShowHealthController.php),
`config('app.version')`.

### System Status

```http
GET /api/status?days=90
```

Public, unauthenticated status page - the human-facing counterpart to the `GET /health`
load-balancer probe. Reports the current state and daily uptime history for every
monitored component (`database`, `cache`, `queue`), read from rows persisted by the
scheduled `health:record` command (every five minutes, except in `local` and `testing`).

`overall_status` is the worst current reading across components; `monitoring_active` is
`false` and readings are `null` before the first recorded run - missing monitoring data is
never reported as an outage. The `days` window defaults to 90 and is capped at 90.
Rate limited per IP via the dedicated `api-status` limiter (`API_STATUS_RATE_LIMIT_PER_MINUTE`,
default **30**).

**Source of Truth:** [SystemStatusController](app/Http/Controllers/SystemHealth/SystemStatusController.php),
[SystemHealthHistoryQuery](app/Queries/SystemHealth/SystemHealthHistoryQuery.php), [routes/console.php](routes/console.php).

## 🖥 API Reference

**Interactive docs (Scalar):** [http://localhost/api/docs](http://localhost/api/docs) - try
endpoints in the browser. Paste a Sanctum bearer token via **Authentication** in the
Scalar UI; `persistAuth` keeps it across reloads. Works in production at
`{APP_URL}/api/docs`.

OpenAPI 3.1 spec: [docs/openapi.yaml](docs/openapi.yaml) (also served at
`/api/openapi.yaml`). Import into Postman or preview offline - see
[docs/api.md](docs/api.md).

<p align="center">
  <img src="docs/images/scalar-docs.png" alt="Scalar interactive API reference showing the Laravel API Starter specification with endpoint navigation" width="960">
</p>

## 🛡 Security

| Area | Implementation | Where |
| --- | --- | --- |
| Authentication | Sanctum bearer tokens (90-day default expiry) | [routes/api.php](routes/api.php) (`auth:sanctum`), `config/api.php` |
| Authorisation | Spatie permissions + Policies | [docs/permissions.md](docs/permissions.md), [app/Policies/](app/Policies/) |
| Rate limiting | 500 req/min API; 5 req/min auth (email+IP, 20/min per-IP ceiling); 10 req/min token creation; 30 req/min public status page (per IP) | `config/api.php`, `bootstrap/app.php` |
| Account recovery | Broker-based reset link with enumeration-neutral responses; reset rotates every credential | [ForgotPasswordController](app/Http/Controllers/Auth/ForgotPasswordController.php), [ResetUserPasswordAction](app/Actions/Auth/ResetUserPasswordAction.php) |
| Two-factor authentication | Email OTP with pending challenges, stateless `two_factor_token` support, and per-route throttles | [SendTwoFactorController](app/Http/Controllers/Auth/SendTwoFactorController.php), [VerifyTwoFactorCodeAction](app/Actions/Auth/VerifyTwoFactorCodeAction.php) |
| Session registry | Cookie-bound device sessions: list, show, per-device revoke, revoke-others, fail-closed store handling, activity tracking, and IP location enrichment (`location_city`/`location_country`, fail-open) | [SessionIndexController](app/Http/Controllers/Sessions/SessionIndexController.php), [DestroyOtherSessionsController](app/Http/Controllers/Sessions/DestroyOtherSessionsController.php), [RegisterWebSessionAction](app/Actions/Sessions/RegisterWebSessionAction.php) |
| CORS | Env-driven allowed origins; local dev-server defaults | `config/cors.php` |
| Input validation | FormRequests; `422` envelope via `ApiResponse` | [app/Support/ApiResponse.php](app/Support/ApiResponse.php) |
| XSS hardening | Plain-text attribute sanitisation on name updates and token names | [SanitisesPlainTextAttributes](app/Http/Requests/Concerns/SanitisesPlainTextAttributes.php) |
| API documentation | Scalar UI at `/api/docs`; optional HTTP Basic Auth | [routes/web.php](routes/web.php), [EnsureCanViewApiDocs](app/Http/Middleware/EnsureCanViewApiDocs.php) |
| Debug tooling | Telescope behind `viewTelescope` gate (Admin only, local only) | [AppServiceProvider](app/Providers/AppServiceProvider.php) |

Report vulnerabilities privately before opening a public issue - see
[SECURITY.md](SECURITY.md). Disclosure contact details are served at
[`/.well-known/security.txt`](public/.well-known/security.txt) (RFC 9116).

## 🏗 Architecture

| Layer | Pattern | Where in This Repo |
| --- | --- | --- |
| Controllers | Single-action invokable (`__invoke`) | [app/Http/Controllers/](app/Http/Controllers/) |
| Form requests | `authorize()` → Policy; allow-lists in `rules()` | [app/Http/Requests/](app/Http/Requests/) |
| DTOs | `final readonly` value objects (`UserFilters`, `IndexSort`) | [app/DataTransferObjects/](app/DataTransferObjects/) |
| Query classes | Sort, filter, include, sparse fieldsets | [app/Queries/](app/Queries/) |
| Actions | Single `execute()` for business operations | [app/Actions/](app/Actions/) |
| Services | Stateless utilities used by Actions | [app/Services/](app/Services/) |
| API resources | Shape every response; sparse-fieldset aware | [app/Http/Resources/](app/Http/Resources/) |
| Policies | Server-side authorisation | [app/Policies/](app/Policies/) |

Shared helpers: [app/Support/](app/Support/) (`ApiResponse`, `ApiDateTime`, `IndexSortParser`,
`SearchTermParser`, `CommaSeparatedList`, `LikePattern`, `QualifiedColumn`). Request concerns:
[app/Http/Requests/Concerns/](app/Http/Requests/Concerns/) (`Parses*QueryParam`,
`ReadsRequestInput`, `ResolvesAuthenticatedViewer`).

### Adding a New Resource

1. Define allow-lists in `app/Queries/{Resource}/{Resource}QueryConstraints.php`
2. Add filter/include query classes and a `{Resource}Filters` DTO
3. Create an `Applies{Resource}Filters` request concern
4. Wire an invokable controller: build DTOs → apply queries → paginate → Resource collection
5. Register the route in [routes/api.php](routes/api.php)
6. Extend [docs/openapi.yaml](docs/openapi.yaml) and verify at [http://localhost/api/docs](http://localhost/api/docs)
7. Update [docs/permissions.md](docs/permissions.md) when a new permission is introduced
8. Cover with unit tests (query builder state, Support parsers) and feature tests (HTTP + database)

Copy [UserIndexController](app/Http/Controllers/Users/UserIndexController.php) as a
template - only allow-lists and filter logic change per resource.

## 📁 File Structure

<details>
<summary><strong>Repository Layout</strong></summary>

```text
app/
├── Actions/              # AuthenticateUserAction, ResetUserPasswordAction, session and token actions, …
├── Console/Commands/     # health:record and other scheduled commands
├── DataTransferObjects/  # UserFilters, TokenFilters, IndexSort, RegisterWebSessionData, …
├── Enums/                # MfaMethod, AuthAuditEvent, RoleName, SystemHealthStatus, …
├── Events/               # AuthEventOccurred, TwoFactorChallengeIssued
├── Http/
│   ├── Controllers/
│   │   ├── Api/          # ShowApiDocsController, ShowOpenApiSpecController, ShowHealthController
│   │   ├── Auth/         # Login, two-factor, registration, password reset, logout
│   │   ├── Sessions/     # Web-session registry endpoints
│   │   ├── SystemHealth/ # Public status page
│   │   └── …             # Users, Clients, Roles, Permissions, Teams, Audit Logs
│   ├── Middleware/       # EnsureSessionVersionMatches, TouchWebSessionActivity, EnsureAccountIsActive, …
│   ├── Requests/         # FormRequests + Parses* concerns
│   └── Resources/        # API Resources (sparse fieldsets)
├── Listeners/            # Queued audit persistence and OTP delivery
├── Notifications/        # Reset link, password-changed alert, two-factor code
├── Policies/             # UserPolicy, WebSessionPolicy, ApiClientPolicy, …
├── Queries/              # *QueryConstraints, *FilterQuery, *IncludeQuery, *SummaryQuery
├── Services/             # PermissionAbilityCatalog, UserAgent parser, SystemHealth checks
└── Support/              # ApiResponse, ApiExceptionRenderer, parsers, auth support, …
config/                   # api.php (limits), useragent.php, cors.php, …
database/
├── factories/            # UserFactory, WebSessionFactory, SystemHealthCheckFactory, …
├── migrations/           # Users, Sessions, Audit Logs, Health Checks, …
└── seeders/              # RolesAndPermissionsSeeder, ApiClientsSeeder
docs/
├── openapi.yaml          # OpenAPI 3.1 - source of truth (served at /api/openapi.yaml)
├── api.md                # Scalar, preview, import, and sync guide
├── permissions.md        # Permission matrix
├── performance.md        # Scale trade-offs
├── releasing.md          # Release runbook
└── testing.md            # Quality gates, suites, coverage floor, pen test
resources/views/          # Scalar embed and welcome page
routes/
├── api.php               # JSON API routes (Sanctum)
├── console.php           # Scheduled commands
└── web.php               # /api/docs and /api/openapi.yaml
scripts/                  # pen-test-auth.sh, semgrep.sh, verify-openapi-examples.sh, …
tests/
├── Concerns/             # AssertsApiEnvelope, MakesStatefulSpaRequests
├── Feature/              # Endpoints, Actions, middleware, listeners, console, policies
└── Unit/                 # Queries, Support, Services, Notifications, Resources (no DB)
```

Trait coverage uses **real hosts** (feature tests and resource unit tests), not
`tests/Support/` harness stubs. See [Testing](#testing).

</details>

## ✅ Quality Gates

Local (Sail - matches PHP 8.5 when host PHP is older):

```bash
./vendor/bin/sail composer lint          # Pint (--test)
./vendor/bin/sail composer lint:fix      # Pint, auto-fix
./vendor/bin/sail composer analyse       # Larastan, level 9
./vendor/bin/sail composer test          # PHPUnit
./vendor/bin/sail composer test:coverage:check   # 90% line-coverage gate
./vendor/bin/sail composer ci            # lint + analyse + coverage + composer audit
```

> [!NOTE]
> `composer ci` does not run OpenAPI example verification. After a seeded DB is up, run
> `./vendor/bin/sail composer verify:openapi` (or `bash scripts/verify-openapi-examples.sh`
> on the host) - CI runs it as a separate parallel job.

[.github/workflows/ci.yml](.github/workflows/ci.yml) runs Pint, Larastan, PHPUnit with
coverage, `composer audit`, Semgrep, and OpenAPI verification on every pull request and
push to `main`. Require the **All Quality Gates** check for branch protection. CI uses
native PHP 8.5 with a MySQL service container - not Sail.

Run Semgrep locally on the host (Docker or a local CLI - not inside Sail):

```bash
bash scripts/semgrep.sh
```

## 🧪 Testing

**Unit tests** ([tests/Unit/](tests/Unit/)) pin logic without a database:

- [tests/Unit/Support/](tests/Unit/Support/) - parse grammar (`IndexSortParser`,
  `SearchTermParser`, `AllowList`, `CommaSeparatedList`)
- [tests/Unit/Queries/](tests/Unit/Queries/) - query builder state (`columns`, `orders`,
  `wheres`)
- [tests/Unit/Services/](tests/Unit/Services/) - user-agent parser, health checks and
  registry, permission catalog
- [tests/Unit/Http/Resources/](tests/Unit/Http/Resources/) - serialisation branches on real
  Resources (e.g. [UserResourceTest](tests/Unit/Http/Resources/UserResourceTest.php))

**Feature tests** ([tests/Feature/](tests/Feature/)) run against a real test database via
`RefreshDatabase` with `Model::preventLazyLoading()` enabled. Sparse-fieldset tests prove
resources never read unselected columns; include tests prove eager loading happened.
[UserIndexControllerTest](tests/Feature/Http/Controllers/Users/UserIndexControllerTest.php)
covers invalid query params via `invalidQueryProvider`; [ApiSecurityProbeTest](tests/Feature/Http/ApiSecurityProbeTest.php)
probes hostile input; [FailClosedRevocationTest](tests/Feature/Actions/Sessions/FailClosedRevocationTest.php)
proves a failing session store still recalls every cookie;
[SystemStatusControllerTest](tests/Feature/Http/Controllers/SystemHealth/SystemStatusControllerTest.php)
covers the public status page; [ListenerRegistrationTest](tests/Feature/Listeners/ListenerRegistrationTest.php)
pins exactly-once audit and OTP dispatch. [ApiDocsTest](tests/Feature/Http/ApiDocsTest.php) covers `/api/docs`,
`/api/openapi.yaml`, and optional HTTP Basic Auth.

**No trait harness classes** - concern behaviour is proved through Support unit tests and
feature or resource tests on production FormRequests and Resources, not `tests/Support/`
stubs.

The full run order, coverage-floor mechanics, and the 41-section adversarial pen test are
documented in [docs/testing.md](docs/testing.md).

## 🚫 What's Not Included

This starter deliberately omits features you would add per product:

- Email verification (registration relies on the email OTP challenge instead)
- OAuth / social login
- TOTP authenticator apps, passkeys, and SMS delivery (email OTP is the only factor)
- Multi-tenancy beyond team row scoping
- File uploads or real-time broadcasting

> [!IMPORTANT]
> Set `API_DOCS_BASIC_AUTH_USER` and `API_DOCS_BASIC_AUTH_PASSWORD` in production if you
> want `/api/docs` behind HTTP Basic Auth instead of public.

## 📄 License

MIT - see [LICENSE](LICENSE).
