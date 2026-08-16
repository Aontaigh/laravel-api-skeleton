# Permissions and Roles

Authorisation uses [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission).
Permission strings are the single source of truth for what a caller may do; Policies
and query scoping enforce them server-side — never on the client alone.

[`database/seeders/RolesAndPermissionsSeeder.php`](../database/seeders/RolesAndPermissionsSeeder.php)
creates every permission below and assigns them to the seeded roles (`Admin`,
`Manager`, `User`, `Service`). Add new permissions there first, then wire them into the relevant
Policy or request concern.

**Policy classes:** [UserPolicy](../app/Policies/UserPolicy.php),
[RolePolicy](../app/Policies/RolePolicy.php),
[PersonalAccessTokenPolicy](../app/Policies/PersonalAccessTokenPolicy.php),
[ApiClientPolicy](../app/Policies/ApiClientPolicy.php),
[AuthAuditLogPolicy](../app/Policies/AuthAuditLogPolicy.php),
[PermissionPolicy](../app/Policies/PermissionPolicy.php).

## Permissions

| Permission | Grants | Enforced In |
| --- | --- | --- |
| `users.list` | Access to `GET /api/users` and `GET /api/users/{user}` | `UserPolicy::viewAny()` and `UserPolicy::view()` |
| `users.list-all` | List users across every team (not just the viewer's team) | [AppliesUserFilters](../app/Http/Requests/Concerns/Users/AppliesUserFilters.php) → [UserFilterQuery](../app/Queries/Users/UserFilterQuery.php) |
| `users.view-email` | See and select the `email` column on user records | [AppliesUserFilters](../app/Http/Requests/Concerns/Users/AppliesUserFilters.php) and [UserResource](../app/Http/Resources/UserResource.php) |
| `users.create` | Create a user via `POST /api/users` | `UserPolicy::create()` |
| `users.update` | Update a user via `PATCH /api/users/{user}` | `UserPolicy::update()` |
| `users.reassign-team` | Reassign `team_id` on `PATCH /api/users/{user}` | `UserPolicy::reassignTeam()` |
| `users.delete` | Soft-delete a user via `DELETE /api/users/{user}` | `UserPolicy::delete()` |
| `users.force-logout` | Force-logout Users via `POST /api/users/logout` | `UserPolicy::forceLogout()` |
| `users.suspend` | Suspend or unsuspend a User via `POST /api/users/{user}/suspend` and `POST /api/users/{user}/unsuspend` | `UserPolicy::suspend()` and `UserPolicy::unsuspend()` |
| `roles.list` | Access to `GET /api/roles` and `GET /api/roles/{role}` | `RolePolicy::viewAny()` and `RolePolicy::view()` |
| `tokens.list-own` | Access to `GET /api/tokens` (own tokens only) | `PersonalAccessTokenPolicy::viewAny()` |
| `tokens.create-own` | Access to `POST /api/tokens` | `PersonalAccessTokenPolicy::create()` |
| `tokens.revoke-own` | Access to `DELETE /api/tokens/{token}` when the token belongs to the caller | `PersonalAccessTokenPolicy::delete()` |
| `tokens.create-for-user` | Access to `POST /api/users/{user}/tokens` (issue a token for another user) | `PersonalAccessTokenPolicy::createForUser()` |
| `api-clients.list` | Access to `GET /api/clients` and `GET /api/clients/{client}` | `ApiClientPolicy::viewAny()` and `ApiClientPolicy::view()` |
| `api-clients.create` | Access to `POST /api/clients` | `ApiClientPolicy::create()` |
| `api-clients.update` | Access to `PATCH /api/clients/{client}` | `ApiClientPolicy::update()` |
| `api-clients.delete` | Access to `DELETE /api/clients/{client}` | `ApiClientPolicy::delete()` |
| `audit-logs.list` | Access to `GET /api/audit-logs` and `GET /api/audit-logs/{auth_audit_log}` (Admin role only for now) | `AuthAuditLogPolicy::viewAny()` and `AuthAuditLogPolicy::view()` |
| `permissions.list` | Access to `GET /api/permissions` | `PermissionPolicy::viewAny()` |

### Notes

#### `GET /me`

Any authenticated interactive User may call `GET /api/me` to load their own profile.
`users.list` is not required — token-only Users use this instead of
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

#### `users.create`

Admin-only creation of interactive user accounts via `POST /api/users`. Assigns
role (`Admin`, `Manager`, or `User`; defaults to `User`) and optional `team_id`.
Email addresses are normalised to lowercase before validation and persistence.
`email_verified_at` remains null and no bearer token is returned. New accounts
are auto-enrolled in email two-factor authentication (`mfa_method: email`).

#### `users.update`

Updates the target user's `name`. Admins may also reassign `team_id` when they
hold `users.reassign-team`. `email` and `password` are not accepted on this
endpoint. Managers may update users on their own team, including their own
account. Regular Users cannot update any account.

#### `users.delete`

Soft-deletes the target user (`deleted_at` is set; the row remains in the database).
Managers may delete users on their own team; Admins may delete any user. Callers
cannot delete their own account through this endpoint. Soft-deleted users are
excluded from the index and return 404 on show.

#### Token Permissions Are Self-Scoped

`tokens.list-own` always returns only the caller's tokens. There is no
`tokens.list-all` — admins issue tokens for others via `tokens.create-for-user` on
`POST /api/users/{user}/tokens`.

#### `GET /api/audit-logs`

Admin-only read-only index of `auth_audit_logs`. The Policy requires the `Admin`
role and rejects service accounts even when `audit-logs.list` is present on the
role. Managers, Users, and Service identities cannot list or show audit rows.

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
cannot suspend their own account — that would leave no one able to lift the
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
| **Manager** | `users.list`, `users.update`, `users.delete`, `roles.list`, `tokens.list-own`, `tokens.create-own`, `tokens.revoke-own`, `permissions.list` |
| **User** | `tokens.list-own`, `tokens.create-own`, `tokens.revoke-own`, `permissions.list` |
| **Service** | `users.list`, `users.list-all`, `users.view-email`, `roles.list` (machine identity only — no interactive login) |

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
