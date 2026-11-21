# Local Development

Get from a clone to a seeded, authenticated API on your machine. The stack runs
through [Laravel Sail](https://laravel.com/docs/sail), so the PHP, Node, MySQL,
and Redis versions match the image and CI.

## Requirements

- Docker Engine or Docker Desktop with Compose.
- Git.
- Free host ports `80` and `5173`, or a remap as described below.
- Optional: host PHP `^8.5.12` with the required extensions if you prefer to
  install Composer dependencies without building the app image first.

The first `composer install` needs a PHP runtime. Build the pinned app image
(PHP 8.5.12) and run Composer inside it; the bind mount writes `vendor/` back to
the host. This keeps the install on the same PHP as the application and avoids a
second, floating bootstrap image.

## First Run

```bash
git clone https://github.com/Aontaigh/laravel-api-skeleton.git
cd laravel-api-skeleton
cp .env.example .env

# Build the pinned app image (PHP 8.5.12) and install Composer dependencies
# inside it. The bind mount writes vendor/ back to the host.
WWWGROUP="$(id -g)" WWWUSER="$(id -u)" docker compose build
WWWGROUP="$(id -g)" WWWUSER="$(id -u)" docker compose run --rm --no-deps \
  --user "$(id -u):$(id -g)" \
  --entrypoint composer \
  -e COMPOSER_HOME=/tmp/composer \
  laravel.test install

./vendor/bin/sail up -d

# node_modules is a named volume owned by the container. Install and build
# through Sail so the container's copy is populated.
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build

./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
```

If host PHP is already `^8.5.12` and has the required extensions, you can run
`composer install` on the host instead of the container bootstrap. Run every
application command through Sail either way.

Open [http://localhost/api/docs](http://localhost/api/docs). The docs page needs a
bearer token, so issue one first.

## Seeded State

`migrate:fresh --seed` creates one team (`Acme Corp`), three human demo users,
and one service account for the demo API client. The human accounts share the
local-only password `password`:

| Email                 | Role    |
| --------------------- | ------- |
| `admin@example.com`   | Admin   |
| `manager@example.com` | Manager |
| `test@example.com`    | User    |

The demo client is `demo-integration-client` with secret `DemoClientSecret12`.
The service account is not a login account. Never use these credentials outside
local development.

## Issue a Token

```bash
curl -s -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}' \
  | jq -r '.data.plain_text_token'
```

Or with Tinker:

```bash
./vendor/bin/sail artisan tinker --execute="echo App\Models\User::where('email', 'admin@example.com')->first()->createToken('local')->plainTextToken;"
```

Paste the token into the Scalar **Authentication** panel at
[http://localhost/api/docs](http://localhost/api/docs), or send it as
`Authorization: Bearer {token}`.

## Ports and APP_URL

`compose.yaml` publishes the app on host port `${APP_PORT:-80}` and Vite on
`${VITE_PORT:-5173}`. A fresh clone uses `80` and `5173`, and `APP_URL` defaults
to `http://localhost`.

If either port is taken, pick free ports in `.env` and point `APP_URL` at the
app port:

```dotenv
APP_PORT=8090
VITE_PORT=5175
APP_URL=http://localhost:8090
```

Then recreate the container:

```bash
docker compose down
./vendor/bin/sail up -d
```

`APP_URL` must name the port the app is actually published on. A mismatch breaks
emailed links, signed URLs, and `url()` calls. Check the pair:

```bash
curl -sf "$(grep -E '^APP_URL=' .env | cut -d= -f2-)/up"
```

Two network namespaces, one port number:

| From              | Base URL                    | Why                                                       |
| ----------------- | --------------------------- | --------------------------------------------------------- |
| Your shell        | `http://localhost:$APP_PORT` | Compose publishes the container's port 80 on this host port |
| Inside Sail       | `http://localhost`           | Port 80 is nginx inside the container; `APP_PORT` does not apply |

`scripts/pen-test-auth.sh` probes from inside Sail. Launch it from the host, not
inside the container.

## Daily Commands

```bash
./vendor/bin/sail up -d            # start
./vendor/bin/sail down             # stop
./vendor/bin/sail shell            # shell in the app container
./vendor/bin/sail artisan ...      # any artisan command
./vendor/bin/sail composer ...     # any composer command
./vendor/bin/sail npm ...          # any npm command
./vendor/bin/sail logs -f          # container logs
./vendor/bin/sail artisan pail     # application log tail
```

Reset the database to the seeded state at any time:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

This wipes local data. Run it before the pen test or after changing seeders.

## Run the Tests

```bash
./vendor/bin/sail composer test
./vendor/bin/sail composer test:coverage:check
```

The full gate order, including the host-only scanners and the pen test, is in
[Testing](../how-to/testing.md).

## Troubleshooting

**`composer install` fails the PHP platform check.** The host PHP is below
8.5.12. Use the Docker bootstrap above, or switch the host runtime to PHP 8.5.12.
Do not relax the constraint.

**`docker compose build` fails on a missing build context.** `compose.yaml`
builds `docker/8.5`, and that directory must contain `Dockerfile`, `php.ini`,
`supervisord.conf`, and `start-container`. Verify with
`git ls-files docker/8.5`.

**`http://localhost` shows a different app.** Another process owns port 80.
Remap `APP_PORT` and `APP_URL` as shown above.

**`MissingAppKeyException`.** `.env.example` ships `APP_KEY=` empty. Run
`./vendor/bin/sail artisan key:generate`.

**`ViteManifestNotFoundException`.** The production build has not run. Run
`./vendor/bin/sail npm run build`, or use `./vendor/bin/sail npm run dev` while
developing.

**Sign-in fails on a plain-HTTP hostname.** `SESSION_SECURE_COOKIE=true` is the
default. Browsers treat `localhost` as a secure origin, but a custom `.test`
hostname over HTTP is not. Set `SESSION_SECURE_COOKIE=false` in your local
`.env` only.

**Stale configuration.** Run `./vendor/bin/sail artisan config:clear` after
editing `.env`.

## Next Steps

- [OpenAPI and Scalar](../how-to/api-docs.md) - Scalar, OpenAPI preview, and client import.
- [Testing](../how-to/testing.md) - the full quality gate order.
- [Contributing](../../CONTRIBUTING.md) - branch, pull request, and review rules.
