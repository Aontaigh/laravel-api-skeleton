# Permissions and Roles

Authorisation uses [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission).
Permission strings are the single source of truth for what a caller may do; Policies
and query scoping enforce them server-side — never on the client alone.

[`database/seeders/RolesAndPermissionsSeeder.php`](../database/seeders/RolesAndPermissionsSeeder.php)
creates every permission below and assigns them to the three seeded roles (`Admin`,
`Manager`, `User`). Add new permissions there first, then wire them into the relevant
Policy or request concern.

**Policy classes:** [UserPolicy](../app/Policies/UserPolicy.php),
[RolePolicy](../app/Policies/RolePolicy.php),
[PersonalAccessTokenPolicy](../app/Policies/PersonalAccessTokenPolicy.php).

## Permissions

| Permission | Grants | Enforced In |
| --- | --- | --- |
| `users.list` | Access to `GET /api/users` and `GET /api/users/{user}` | `UserPolicy::viewAny()` and `UserPolicy::view()` |
| `users.list-all` | List users across every team (not just the viewer's team) | [AppliesUserFilters](../app/Http/Requests/Concerns/Users/AppliesUserFilters.php) → [UserFilterQuery](../app/Queries/Users/UserFilterQuery.php) |
| `users.view-email` | See and select the `email` column on user records | [AppliesUserFilters](../app/Http/Requests/Concerns/Users/AppliesUserFilters.php) and [UserResource](../app/Http/Resources/UserResource.php) |
| `users.update` | Update a user via `PATCH /api/users/{user}` | `UserPolicy::update()` |
| `users.reassign-team` | Reassign `team_id` on `PATCH /api/users/{user}` | `UserPolicy::reassignTeam()` |
| `users.delete` | Soft-delete a user via `DELETE /api/users/{user}` | `UserPolicy::delete()` |
| `roles.list` | Access to `GET /api/roles` and `GET /api/roles/{role}` | `RolePolicy::viewAny()` and `RolePolicy::view()` |
| `tokens.list-own` | Access to `GET /api/tokens` (own tokens only) | `PersonalAccessTokenPolicy::viewAny()` |
| `tokens.create-own` | Access to `POST /api/tokens` | `PersonalAccessTokenPolicy::create()` |
| `tokens.revoke-own` | Access to `DELETE /api/tokens/{token}` when the token belongs to the caller | `PersonalAccessTokenPolicy::delete()` |
| `tokens.create-for-user` | Access to `POST /api/users/{user}/tokens` (issue a token for another user) | `PersonalAccessTokenPolicy::createForUser()` |

### Notes

#### `users.list` vs `users.list-all`

`users.list` gates the endpoint; `users.list-all` controls row scope. A Manager holds
`users.list` but not `users.list-all`, so they only see users on their own team.

#### `users.view-email`

Even when `fields[users]` is omitted (default column projection), `email` is stripped
from the response unless the viewer holds this permission. The check lives in
`UserResource`, not only in the query allow-list.

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

## Roles

| Role | Permissions |
| --- | --- |
| **Admin** | All permissions |
| **Manager** | `users.list`, `users.update`, `users.delete`, `roles.list`, `tokens.list-own`, `tokens.create-own`, `tokens.revoke-own` |
| **User** | `tokens.list-own`, `tokens.create-own`, `tokens.revoke-own` |

## Seeded Accounts

After `migrate:fresh --seed`:

| Email | Role |
| --- | --- |
| `admin@example.com` | Admin |
| `manager@example.com` | Manager |
| `test@example.com` | User |

## Adding a Permission

1. Add the string to `RolesAndPermissionsSeeder::PERMISSIONS` and assign it in
   `ROLE_PERMISSIONS`.
2. Document it in the table above.
3. Enforce it in a Policy method (or `FormRequest::authorize()` delegating to one).
4. Cover the allow and deny paths in feature tests.
