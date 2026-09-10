# Permissions and Roles

Authorisation uses [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission).
Permission strings are the single source of truth for what a caller may do; Policies
and query scoping enforce them server-side - never on the client alone.

[`database/seeders/RolesAndPermissionsSeeder.php`](../database/seeders/RolesAndPermissionsSeeder.php)
creates every permission below and assigns them to the seeded roles (`Admin`,
`Manager`, `User`, `Service`). Add new permissions there first, then wire them into the relevant
Policy or request concern.

**Policy classes:** [UserPolicy](../app/Policies/UserPolicy.php),
[RolePolicy](../app/Policies/RolePolicy.php),
[PersonalAccessTokenPolicy](../app/Policies/PersonalAccessTokenPolicy.php),
[ApiClientPolicy](../app/Policies/ApiClientPolicy.php),
[AuthAuditLogPolicy](../app/Policies/AuthAuditLogPolicy.php),
[PermissionPolicy](../app/Policies/PermissionPolicy.php),
[TeamPolicy](../app/Policies/TeamPolicy.php),
[WebSessionPolicy](../app/Policies/WebSessionPolicy.php),
[WebhookEndpointPolicy](../app/Policies/WebhookEndpointPolicy.php).

## Permissions

| Permission | Grants | Enforced In |
| --- | --- | --- |
| `users.list` | Access to `GET /api/users` and `GET /api/users/{user}` | `UserPolicy::viewAny()` and `UserPolicy::view()` |
| `users.list-all` | List users across every team (not just the viewer's team) | [AppliesUserFilters](../app/Http/Requests/Concerns/Users/AppliesUserFilters.php) → [UserFilterQuery](../app/Queries/Users/UserFilterQuery.php) |
| `users.view-email` | See and select the `email` column on user records | [AppliesUserFilters](../app/Http/Requests/Concerns/Users/AppliesUserFilters.php) and [UserResource](../app/Http/Resources/UserResource.php) |
| `users.create` | Create a user via `POST /api/users` | `UserPolicy::create()` |
| `users.update` | Update a user via `PATCH /api/users/{user}` | `UserPolicy::update()` |
| `users.assign-role` | Change `role` on `PATCH /api/users/{user}` | `UserPolicy::assignRole()` |
| `users.reassign-team` | Reassign `team_id` on `PATCH /api/users/{user}` | `UserPolicy::reassignTeam()` |
| `users.delete` | Soft-delete a user via `DELETE /api/users/{user}` | `UserPolicy::delete()` |
| `users.force-logout` | Force-logout Users via `POST /api/users/logout` | `UserPolicy::forceLogout()` |
| `users.suspend` | Suspend or unsuspend a User via `POST /api/users/{user}/suspend` and `POST /api/users/{user}/unsuspend` | `UserPolicy::suspend()` and `UserPolicy::unsuspend()` |
| `roles.list` | Access to `GET /api/roles` and `GET /api/roles/{role}` | `RolePolicy::viewAny()` and `RolePolicy::view()` |
| `tokens.list-own` | Access to `GET /api/tokens` (own tokens only) | `PersonalAccessTokenPolicy::viewAny()` |
| `tokens.create-own` | Access to `POST /api/tokens` | `PersonalAccessTokenPolicy::create()` |
| `tokens.revoke-own` | Access to `DELETE /api/tokens/{token}` when the token belongs to the caller | `PersonalAccessTokenPolicy::delete()` |
| `tokens.create-for-user` | Access to `POST /api/users/{user}/tokens` (issue a token for another user) | `PersonalAccessTokenPolicy::createForUser()` |
| `sessions.list-own` | Access to `GET /api/sessions` (own sessions only) | `WebSessionPolicy::viewAny()` |
| `sessions.list-all` | List web sessions across every User (not just the caller's) | [AppliesSessionFilters](../app/Http/Requests/Concerns/Sessions/AppliesSessionFilters.php) → [SessionFilterQuery](../app/Queries/Sessions/SessionFilterQuery.php) |
| `sessions.revoke-own` | Access to `DELETE /api/sessions/{web_session}`, `DELETE /api/sessions/current`, and `DELETE /api/sessions/others` when the sessions belong to the caller | `WebSessionPolicy::delete()` |
| `sessions.revoke-any` | Revoke any User's web session via `DELETE /api/sessions/{web_session}` | `WebSessionPolicy::delete()` |
| `api-clients.list` | Access to `GET /api/clients` and `GET /api/clients/{client}` | `ApiClientPolicy::viewAny()` and `ApiClientPolicy::view()` |
| `api-clients.create` | Access to `POST /api/clients` | `ApiClientPolicy::create()` |
| `api-clients.update` | Access to `PATCH /api/clients/{client}` and `POST /api/clients/{client}/rotate-secret` | `ApiClientPolicy::update()` |
| `webhooks.list` | Access to `GET /api/webhook-endpoints`, `GET /api/webhook-endpoints/{webhook_endpoint}`, and `GET /api/webhook-endpoints/{webhook_endpoint}/deliveries` | `WebhookEndpointPolicy::viewAny()` and `WebhookEndpointPolicy::view()` |
| `webhooks.create` | Access to `POST /api/webhook-endpoints` | `WebhookEndpointPolicy::create()` |
| `webhooks.update` | Access to `PATCH /api/webhook-endpoints/{webhook_endpoint}`, test pings, and secret rotation | `WebhookEndpointPolicy::update()` |
| `webhooks.delete` | Access to `DELETE /api/webhook-endpoints/{webhook_endpoint}` | `WebhookEndpointPolicy::delete()` |
| `api-clients.delete` | Access to `DELETE /api/clients/{client}` | `ApiClientPolicy::delete()` |
| `audit-logs.list` | Access to `GET /api/audit-logs` and `GET /api/audit-logs/{auth_audit_log}` (Admin role only for now) | `AuthAuditLogPolicy::viewAny()` and `AuthAuditLogPolicy::view()` |
| `teams.list` | Access to `GET /api/teams` and `GET /api/teams/{team}` | `TeamPolicy::viewAny()` and `TeamPolicy::view()` |
| `teams.create` | Access to `POST /api/teams` | `TeamPolicy::create()` |
| `teams.update` | Access to `PATCH /api/teams/{team}` | `TeamPolicy::update()` |
| `teams.delete` | Access to `DELETE /api/teams/{team}` | `TeamPolicy::delete()` |
| `permissions.list` | Access to `GET /api/permissions` | `PermissionPolicy::viewAny()` |

### Notes

#### `GET /me`

Any authenticated interactive User may call `GET /api/me` to load their own profile.
`users.list` is not required - token-only Users use this instead of
`GET /api/users/{id}`. `UserPolicy::viewMe()` denies service accounts. The
response always includes the caller's `email` and supports the same `include` and
`fields[…]` allow-lists as User show.

#### `users.list` vs `users.list-all`

`users.list` gates the endpoint; `users.list-all` controls row scope. A Manager holds
`users.list` but not `users.list-all`, so they only see users on their own team.

#### `users.view-email`

Even when `fields[users]` is omitted (default column projection), `email` is stripped
from the response unless the viewer holds this permission. The check lives in
`UserResource`, not only in the query allow-list.

#### `sessions.list-all` and session telemetry

`user_id` is only exposed when the viewer holds `sessions.list-all`. `ip_address` and
`user_agent` are always returned for the caller's own sessions; cross-user telemetry
requires `sessions.list-all` (admin session management). The checks live in
`WebSessionResource`, not only in the query allow-list - omitting `fields[sessions]`
runs an unqualified `SELECT *` and would otherwise leak those columns.

#### `GET /api/sessions/{web_session}` and `DELETE /api/sessions/others`

Single-session show needs no new permission: the scoped `{web_session}` binding
404s out-of-scope rows and `WebSessionPolicy::view()` mirrors the revoke scope
(`sessions.list-all` sees all, everyone else only their own). Revoked rows stay
invisible on show, matching the index. `DELETE /api/sessions/others` ("sign out
other devices") is gated by `sessions.revoke-own` and only ever touches the
caller's own rows - bearer tokens are untouched and the current browser stays
signed in.

#### `users.create`

Admin-only creation of interactive user accounts via `POST /api/users`. Assigns
role (`Admin`, `Manager`, or `User`; defaults to `User`), optional `team_id`,
and optional `phone` (display forms are compacted to canonical E.164 before
validation). Email addresses are normalised to lowercase before validation and persistence.
`email_verified_at` remains null and no bearer token is returned. New accounts
are auto-enrolled in email two-factor authentication (`mfa_method: email`).

#### `users.update`

Updates the target user's `name` and `phone`. Admins may also reassign `team_id` when they
hold `users.reassign-team`, and `role` when they hold `users.assign-role`. `email` and
`password` are not accepted on this endpoint. Managers may update users on their own team, including their own
account. Regular Users cannot update any account.

#### `users.assign-role`

Admin-only role changes via the `role` field on `PATCH /api/users/{user}`
(`Admin`, `Manager`, or `User`; enforced with the same `prohibitedIf` pattern
as `team_id`). Callers cannot change their own role or the role of a service
account (the field is prohibited, so both answer `422`), and demoting the last
remaining Admin answers `422`
(domain guard in `UpdateUserAction`, so the API can never strand itself with
no administrator).

#### `users.delete`

Soft-deletes the target user (`deleted_at` is set; the row remains in the database).
Managers may delete users on their own team; Admins may delete any user. Callers
cannot delete their own account through this endpoint. Soft-deleted users are
excluded from the index and return 404 on show.

#### Teams

Team listing is shared with Managers (`teams.list` on Admin and Manager), while
creation, update, and deletion are Admin-only (`teams.create`, `teams.update`,
`teams.delete`). `DELETE /api/teams/{team}` answers `422` while the Team still
has assigned Users - the guard lives in `DeleteTeamAction`, so members are never
silently un-scoped to `team_id` null. Reassign or remove the members first.

#### Webhooks

Outbound delivery is Admin-only (`webhooks.list`, `webhooks.create`,
`webhooks.update`, `webhooks.delete`), mirroring API client management:
integrations are configured by administrators, not self-service. The deliveries
index, test pings, and rotation need no extra permission beyond the endpoint's
own. The signing secret is never serialised - it leaves the API once on create
and rotate, and lives encrypted at rest (`encrypted` cast: the delivery job must
recover the plaintext to compute the HMAC, unlike a client secret that is only
ever compared). Receiving is at-least-once, so integrators must treat
`Webhook-Id` as an idempotency key.

#### Token Permissions Are Self-Scoped

`tokens.list-own` always returns only the caller's tokens. There is no
`tokens.list-all` - admins issue tokens for others via `tokens.create-for-user` on
`POST /api/users/{user}/tokens`.

#### `GET /api/audit-logs`

Admin-only read-only index of `auth_audit_logs`. The Policy requires the `Admin`
role and rejects service accounts even when `audit-logs.list` is present on the
role. Managers, Users, and Service identities cannot list or show audit rows.

The log covers authentication (login, logout, registration, 2FA, recovery,
email verification) and access-control changes: password changes, role changes,
suspensions, session revokes, token issuance and revocation, API client
lifecycle, and webhook endpoint lifecycle (create, update, delete, secret
rotation - the target URL is an attacker-controlled exfiltration channel, so
every configuration change is audited). Plain resource administration (user
create/rename/delete, team CRUD)
stays out by design, so incident response is never buried under admin noise.
User-targeted rows carry the affected account; token and client rows carry the
acting Admin alongside the issued credential ID where one exists.

#### `GET /api/permissions`

Read-only catalog of every Spatie permission string the application registers.
Interactive Users who create Personal Access Tokens (`permissions.list` on Admin,
Manager, and User) use this to populate ability pickers. Results are scoped to
the `web` guard and validated against the same catalog
[PermissionAbilityCatalog](../app/Services/Permissions/PermissionAbilityCatalog.php)
enforces on token and API client create. Service accounts cannot list permissions.

#### Suspended accounts

`suspended_at` blocks every authenticated route via the `active.account` middleware
(`403 Account Suspended`). Password login and OAuth client-credentials exchange
reject suspended identities up front with the generic `Invalid Credentials`
validation message (same as a wrong password) so callers cannot obtain a token
that only fails on the next request. Remember-me restoration answers with a
generic `401 Unauthenticated`.

Admins suspend and unsuspend accounts via `POST /api/users/{user}/suspend` and
`POST /api/users/{user}/unsuspend`, both gated by `users.suspend`. An Admin
cannot suspend their own account - that would leave no one able to lift the
suspension. Suspending a service account disables its API clients'
client-credentials exchange (the exchange rejects suspended identities).

#### Service Accounts and API Clients

Machine-to-machine callers use **API clients** (OAuth2 `client_credentials`) rather than
password login:

- `POST /api/oauth/token` with `grant_type`, `client_id`, and `client_secret` issues a
  scoped Sanctum bearer token (default **30**-day lifetime via `API_CLIENT_TOKEN_EXPIRATION_DAYS`).
- Each client is linked to a **service User** (`is_service_account = true`) with the
  `Service` role. Token abilities are stored on the client and further scope API access.
- Service accounts cannot log in, self-issue tokens, or be force-logged out.
- E-Mail verification never applies to service accounts: they authenticate via
  client credentials and have no mailbox to verify, so the `email.verified` gate
  lets them through and only interactive Users are held at `403` until confirmed.
- Admins manage clients via `GET /api/clients`, `POST /api/clients`,
  `PATCH /api/clients/{client}`, and `DELETE /api/clients/{client}`. The plaintext
  `client_secret` is returned once on create.

After `migrate:fresh --seed`, a demo client is available:

| `client_id` | `client_secret` (local default) |
| --- | --- |
| `demo-integration-client` | `DemoClientSecret12` |

## Roles

| Role | Permissions |
| --- | --- |
| **Admin** | All permissions |
| **Manager** | `users.list`, `users.update`, `users.delete`, `roles.list`, `teams.list`, `tokens.list-own`, `tokens.create-own`, `tokens.revoke-own`, `permissions.list`, `sessions.list-own`, `sessions.revoke-own` |
| **User** | `tokens.list-own`, `tokens.create-own`, `tokens.revoke-own`, `permissions.list`, `sessions.list-own`, `sessions.revoke-own` |
| **Service** | `users.list`, `users.list-all`, `users.view-email`, `roles.list` (machine identity only - no interactive login) |

## Seeded Accounts

After `migrate:fresh --seed`:

| Email | Role |
| --- | --- |
| `admin@example.com` | Admin |
| `manager@example.com` | Manager |
| `test@example.com` | User |
| `integrations@clients.internal` | Service (demo API client) |

Demo client credentials: `client_id` `demo-integration-client`, secret `DemoClientSecret12`
(override via `API_DEMO_CLIENT_SECRET`).

## Adding a Permission

1. Add the string to `RolesAndPermissionsSeeder::PERMISSIONS` and assign it in
   `ROLE_PERMISSIONS`.
2. Document it in the table above.
3. Enforce it in a Policy method (or `FormRequest::authorize()` delegating to one).
4. Cover the allow and deny paths in feature tests.
