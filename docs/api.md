# API Reference

Machine-readable contract: [openapi.yaml](openapi.yaml) (OpenAPI 3.1), served at
**`/api/openapi.yaml`** in every environment.

Human-oriented endpoint guides and permission notes live in the [README](../README.md)
and [permissions.md](permissions.md). The OpenAPI spec is the source of truth for
tooling — Postman, Insomnia, client codegen, contract linting, and the hosted Scalar UI.

Allow-lists in the spec mirror the PHP constraints in
[`app/Queries/*/*QueryConstraints.php`](../app/Queries/) — when they drift, fix
the spec and the constraints in the same change.

## Interactive Docs (Scalar)

Hosted in the app at **`/api/docs`** in every environment (local and production).
Scalar loads the spec from `/api/openapi.yaml` on the same host, so try-it requests
stay same-origin — no CORS configuration needed.

1. Start the app (`./vendor/bin/sail up -d`)
2. Open [http://localhost/api/docs](http://localhost/api/docs) (or `{APP_URL}/api/docs` in production)
3. Issue a token (see [README Quick Start](../README.md#quick-start))
4. Click **Authentication** in Scalar → paste `Bearer {token}` → try any endpoint

**Production lock-down:** set `API_DOCS_BASIC_AUTH_USER` and
`API_DOCS_BASIC_AUTH_PASSWORD` in `.env`. Both routes (`/api/docs` and
`/api/openapi.yaml`) then require HTTP Basic Auth. API endpoints remain protected
by Sanctum regardless.

| Piece | Location |
| --- | --- |
| Scalar page route | [routes/web.php](../routes/web.php) → `GET /api/docs` |
| OpenAPI file route | [routes/web.php](../routes/web.php) → `GET /api/openapi.yaml` |
| Docs controller | [ShowApiDocsController](../app/Http/Controllers/Api/ShowApiDocsController.php) |
| Spec controller | [ShowOpenApiSpecController](../app/Http/Controllers/Api/ShowOpenApiSpecController.php) |
| Scalar Blade view | [api-docs.blade.php](../resources/views/api-docs.blade.php) |
| Optional Basic Auth | [EnsureCanViewApiDocs](../app/Http/Middleware/EnsureCanViewApiDocs.php) |
| Spec path config | `config/api.php` → `openapi_spec` |
| Feature tests | [ApiDocsTest](../tests/Feature/Http/ApiDocsTest.php) |

## Learning Path

1. [README Quick Start](../README.md#quick-start) — Sail, seed, issue a token
2. **[/api/docs](http://localhost/api/docs)** — Scalar try-it UI (paste bearer token)
3. [README API](../README.md#api) — shared query contract and per-resource summaries
4. [openapi.yaml](openapi.yaml) — full paths, schemas, and param allow-lists
5. [permissions.md](permissions.md) — who can call each endpoint
6. Source — [routes/api.php](../routes/api.php), controllers under
   [app/Http/Controllers/](../app/Http/Controllers/)

## View the Docs

**Scalar (recommended)** — interactive try-it UI, served by the app:

- Local: [http://localhost/api/docs](http://localhost/api/docs)
- Production: `{APP_URL}/api/docs`

**Swagger Editor** — paste or import [openapi.yaml](openapi.yaml):

<https://editor.swagger.io>

**Redoc** (read-only HTML):

```bash
npx @redocly/cli preview-docs docs/openapi.yaml
```

**Stoplight Elements** — drop the file into [Stoplight Studio](https://stoplight.io/studio)
or serve Elements against the spec URL.

## Import Into a Client

1. Open Postman / Insomnia / Bruno
2. Import → OpenAPI 3.1 → select `docs/openapi.yaml` **or** fetch `{APP_URL}/api/openapi.yaml`
3. Set the collection variable for `Authorization: Bearer {token}`

Issue a local token:

```bash
./vendor/bin/sail artisan tinker --execute="echo App\Models\User::where('email', 'admin@example.com')->first()->createToken('docs')->plainTextToken;"
```

## Browser Frontends (CORS)

Cross-origin browser clients need CORS when the frontend origin differs from
origin differs from the API host. Laravel's `HandleCors` middleware is enabled by
default; paths are `api/*` and `sanctum/csrf-cookie`.

**Bearer tokens (recommended):** send `Authorization: Bearer {token}` from your
frontend. Set `CORS_ALLOWED_ORIGINS` in production to your app URL(s). Local and
testing environments allow common dev-server origins (`localhost:3000`, `:5173`) when
the env var is unset.

```bash
# .env (production)
CORS_ALLOWED_ORIGINS=https://app.example.com,https://www.example.com
```

**axios example:**

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL, // e.g. http://localhost/api
  headers: { Authorization: `Bearer ${token}` },
});

const { data } = await api.get('/users');
```

**Sanctum cookie / CSRF SPA auth (optional):** set `CORS_SUPPORTS_CREDENTIALS=true`,
list the frontend origin in `CORS_ALLOWED_ORIGINS`, align `SANCTUM_STATEFUL_DOMAINS`,
and call `GET /sanctum/csrf-cookie` before login. This skeleton defaults to
bearer-token auth; cookie mode is documented for teams that adopt Sanctum's SPA flow.

| Piece | Location |
| --- | --- |
| CORS config | [config/cors.php](../config/cors.php) |
| Stateful SPA domains | [config/sanctum.php](../config/sanctum.php) → `stateful` |
| Feature tests | [ApiCorsTest](../tests/Feature/Http/ApiCorsTest.php) |

## Keeping the Spec in Sync

When you add or change an endpoint:

1. Update [openapi.yaml](openapi.yaml) (paths, schemas, allow-lists) — use Title Case for `##` headings, operation `summary` values, response `description` labels, and `**Bold Labels:**` in prose (see [write-readme](https://github.com/kaxmedia/ai-rules/blob/main/skills/write-readme/SKILL.md))
2. Update the matching `*QueryConstraints` class under [app/Queries/](../app/Queries/)
3. Open [http://localhost/api/docs](http://localhost/api/docs) and spot-check the changed operation
4. Update the README `## API` section if behaviour is user-facing
5. Update [permissions.md](permissions.md) when a new permission is introduced
6. Run [ApiDocsTest](../tests/Feature/Http/ApiDocsTest.php) and the full test suite
7. Re-verify response examples against a running app:
   `./vendor/bin/sail bash scripts/verify-openapi-examples.sh` (requires Sail + seeded DB)
8. Optional: lint the spec in CI with [Spectral](https://stoplight.io/open-source/spectral)

The spec deliberately documents the shared index query contract (`sort`,
`fields[{resource}]`, `include`, `filter[search]`, pagination) in detail — that
surface is hard for code generators to infer and is where hand-written OpenAPI
pays off.
