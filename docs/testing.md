# Testing

The full suite of checks to run before opening a pull request: PHPUnit suites,
the coverage floor, static analysis, OpenAPI example verification, Semgrep,
Zizmor, and the adversarial auth pen test. Run everything through Sail so the
PHP version matches CI; CI ([.github/workflows/ci.yml](../.github/workflows/ci.yml))
runs the same gates as parallel jobs behind one **All Quality Gates** check.

## Prerequisites

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan migrate:fresh --seed   # fresh state for the pen test
```

The unit suite runs with `QUEUE_CONNECTION=sync` (set in `phpunit.xml`), so
queued listeners (audit writes, notifications) execute inline and are
assertable without a worker.

### What a Fresh Seed Gives You

`migrate:fresh --seed` starts from a known, deliberately small state: **4
Users, 1 API client, 4 Roles, and 0 Auth Audit Logs, Web Sessions, or Webhook
Endpoints.** Two things about that state surprise people:

- **A User has no `status` attribute.** `filter[status]` is _derived_ from
  `suspended_at` and `deleted_at`, which is why a seeded User reads back with
  no status: it is active because both columns are null, not because a column
  says so. The filter's three values map to those columns - `active` is
  `suspended_at` null and not trashed, `suspended` is `suspended_at` set and
  not trashed, `deleted` is trashed (and needs `deleted` in the list before the
  query drops its soft-delete scope).
- **The empty tables are the point.** Several probes assert on the _absence_ of
  rows, so seeding extra Auth Audit Logs by hand can make a probe fail for a
  reason unrelated to the probe. Add fixtures only when a probe asks for them.

CI seeds with `artisan migrate --force --seed` rather than `migrate:fresh`,
because a CI database is already empty. Locally, `migrate:fresh` is what
guarantees the state above.

### Two Network Namespaces, One Port Number

This trips up nearly everyone, including agents:

| Where You Run It                                                                     | Base URL                         | Why                                                                                                                    |
| ------------------------------------------------------------------------------------ | -------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **On the host** (`curl`, a browser, `httpClient`)                                    | `http://localhost:$APP_PORT/api` | `compose.yaml` publishes the container's port 80 as host port `${APP_PORT:-80}`, so read the real one from your `.env` |
| **Inside Sail** (`sail exec ... curl`, and everything in `scripts/pen-test-auth.sh`) | `http://localhost/api`           | port 80 is nginx _inside_ the container, always - `APP_PORT` never affects it                                          |

A fresh clone ships `APP_URL=http://localhost` and no `APP_PORT`, so both rows
resolve to port 80. If you remapped ports to free up 80 (see the port-collision
entry in the [README](../README.md)), only the **host** row moves.

`scripts/pen-test-auth.sh` runs its probes through `sail exec`, so its default
`PEN_TEST_BASE=http://localhost/api` is correct **from inside the container**.
Probing port 80 from your shell returns nothing, and probing your host port from
inside the container returns nothing - neither is a broken app. Check both
namespaces, deriving the host port so the command works whatever you remapped:

```bash
HP="$(grep -E '^APP_PORT=' .env | cut -d= -f2)"; HP="${HP:-80}"
curl -s -o /dev/null -w '%{http_code}\n' "http://localhost:${HP}/api/users"     # host
./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w '%{http_code}\n' \
  http://localhost/api/users                                                   # in container
```

Both answer `401` because the endpoint needs a token; a `000` means you used the
wrong namespace, not that the API is down.

## Quality Gates

Run in this order; stop at the first failure.

Set this once, so the pinned scanner images below stay in step with `ci.yml`:

```bash
export ZIZMOR_IMAGE=ghcr.io/zizmorcore/zizmor@sha256:a2eb396d886c053073405c7a980f2139ba2248ec172243cfa3841e57196e8101
export ACTIONLINT_IMAGE=rhysd/actionlint@sha256:b1934ee5f1c509618f2508e6eb47ee0d3520686341fec936f3b79331f9315667
```

**Everything except step 7 runs through `./vendor/bin/sail`.** The project requires
`^8.5`, and a host PHP older than that fails inside `platform_check.php` before any
code loads, so `php scripts/...` on the host is not interchangeable with
`./vendor/bin/sail php scripts/...`. Switch the host runtime (`herd use php@8.5`,
`asdf local php 8.5`) if you would rather not use Sail.

| Step | Run It          | Command                                                                                                         | Checks                                                                                             | Pass Condition                                    |
| ---- | --------------- | --------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| 1    | Sail            | `./vendor/bin/sail composer lint`                                                                               | Pint style check (`lint:fix` to auto-fix)                                                          | Exit 0                                            |
| 1b   | Sail            | `./vendor/bin/sail composer lint:links`                                                                         | Markdown link and anchor resolution                                                                | `Link Lint Passed`                                |
| 2    | Sail            | `./vendor/bin/sail composer analyse`                                                                            | Larastan at level 10 with strict, deprecation, and PHPUnit rules ([phpstan.neon](../phpstan.neon)) | `No errors`                                       |
| 3    | Sail            | `./vendor/bin/sail composer test`                                                                               | Full PHPUnit suite (unit + feature)                                                                | All pass                                          |
| 4    | Sail            | `./vendor/bin/sail composer test:coverage:check`                                                                | Step 3 plus the 90% line-coverage gate over `app/`                                                 | `Coverage Gate Passed`, and `PHPUnit Warnings: 0` |
| 5    | Sail            | `./vendor/bin/sail composer verify:version`                                                                     | `composer.json`, `package.json`, and the App version agree                                         | `is in Sync`                                      |
| 6    | Sail            | `./vendor/bin/sail composer audit --locked`                                                                     | Known Composer advisories                                                                          | `No security vulnerability advisories found`      |
| 7    | **host**        | `bash scripts/semgrep.sh`                                                                                       | SAST with Laravel security rules                                                                   | `0 findings`                                      |
| 8    | host            | `docker run --rm -v "$PWD:/repo:ro" "$ZIZMOR_IMAGE" --config /repo/.github/zizmor.yml /repo/.github/workflows/` | GitHub Actions workflow audit, scoped to `.github/workflows/`                                      | Exit 0                                            |
| 9    | host            | `docker run --rm -v "$PWD:/repo:ro" -w /repo "$ACTIONLINT_IMAGE"`                                               | Workflow syntax (`actionlint`)                                                                     | No output                                         |
| 10   | host            | `npx --yes -p renovate renovate-config-validator`                                                               | `renovate.json` schema                                                                             | Exit 0                                            |
| 11   | Sail, seeded DB | `./vendor/bin/sail composer verify:openapi`                                                                     | Replays every example in [openapi.yaml](openapi.yaml) against a live app                           | `All OpenAPI Examples Verified`                   |
| 12   | Sail, seeded DB | `bash scripts/pen-test-auth.sh`                                                                                 | Live adversarial HTTP probes (see below)                                                           | `Fail: 0`                                         |

Steps 8 to 10 run on the host because each needs a Docker CLI the app container does
not have; step 7 runs there for the same reason, as the Semgrep engine is an image.

Zizmor needs both the pinned image and CI's scope to reproduce CI:

- **Use the pinned image.** `ZIZMOR_IMAGE` is exported at the top of this page and
  defined in [ci.yml](../.github/workflows/ci.yml). `ghcr.io/woodruffw/zizmor:latest` reports 11
  findings the pinned image does not, so `:latest` turns a green gate red for no reason.
- **Scope it to `.github/workflows/`.** Pointing it at the repository root also reports
  findings from the vendored toolkit and exits non-zero.

CI runs eleven of the twelve steps as nine parallel jobs, behind one `All Quality Gates`
summary job. `All Quality Gates` fails when any other job fails, is cancelled, or is
skipped - so a cancelled job fails the build even though nothing was reported as broken.

`composer ci` chains `lint`, `lint:links`, `analyse`, `semgrep`, `test:coverage:check`,
`verify:openapi`, `verify:version`, and `composer audit --locked`. It does **not** cover
Zizmor, `actionlint`, or the Renovate config, and the live pen test (step 12) runs
locally only - so it is a pre-flight check rather than a full substitute for CI.

> [!IMPORTANT]
> `composer ci` includes `verify:openapi`, which needs a running app and a seeded
> database. Run `./vendor/bin/sail artisan migrate:fresh --seed` first and make sure the
> stack serves HTTP on `localhost`, or the chain fails on step 11 of 12 rather than on
> anything you changed.

> [!NOTE]
> Step 11's admin login queues an audit write - with `QUEUE_CONNECTION=redis`, drain the
> queue once before verifying:
> `./vendor/bin/sail artisan queue:work --stop-when-empty`

> [!NOTE]
> These scripts **write to the working tree and to the database**, so re-seed between
> runs:
>
> - `scripts/pen-test-auth.sh` rotates the demo client's secret and writes
>   `storage/app/pen-test-body.json` and `storage/app/pen-test-cookies.txt`. Without a
>   fresh seed, `ClientShowSuccess` fails and step 12 also exercises the seeded demo
>   client, so re-seed after step 11 and before step 12.
> - `scripts/semgrep.sh --sarif --output semgrep.sarif` writes a SARIF file at the repo
>   root; the default invocation writes nothing.
> - Step 12 creates fixture Users named `probe.filter.a`, `probe.filter.b`, and
>   `probe.filter.c` on every run.

> [!NOTE]
> Do not add baseline entries to silence new static-analysis findings - the typed
> accessors and narrowed properties the codebase uses exist to keep level 10 green.

> [!NOTE]
> On hosts where composer lacks `Composer\Config::disableProcessTimeout` (anything not
> invoked through the repo's `composer dev` wrapper), step 4 dies at composer's
> 300-second process timeout. Run the two halves directly:
> `./vendor/bin/sail artisan test --coverage-clover=storage/coverage/clover.xml`
> then `./vendor/bin/sail php scripts/check-coverage-threshold.php 90`.

## Test Suites

Two suites, one rule each: **unit tests never touch the database, feature tests
always do.**

| Suite            | Base Class                                               | Database                                         | Covers                                                                  |
| ---------------- | -------------------------------------------------------- | ------------------------------------------------ | ----------------------------------------------------------------------- |
| `tests/Unit/`    | [UnitTestCase](../tests/UnitTestCase.php)                | Forbidden - any query fails the test at teardown | Query builders, Support parsers, DTOs, checks, notification mail bodies |
| `tests/Feature/` | [TestCase](../tests/TestCase.php) with `RefreshDatabase` | Real MySQL (`testing` database)                  | HTTP endpoints, Actions, middleware, console commands, queued listeners |

## What the Suite Covers

| Area               | Where                                                     | What Is Exercised                                                                                                                                                                                                                     |
| ------------------ | --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| HTTP endpoints     | `tests/Feature/Http/Controllers/`                         | Every route in [routes/api.php](../routes/api.php): auth, two-factor, password reset, email verification, sessions, users, tokens, API clients, audit logs, roles, permissions, teams, webhooks, CSP reports, app-info, system status |
| Actions            | `tests/Feature/Actions/`                                  | Registration, credential finalisation, token creation, session revocation, password change, user and team admin - against the real database                                                                                           |
| Middleware         | `tests/Feature/Http/Middleware/`                          | Session-version gate, account-active gate, session-activity touch, security headers                                                                                                                                                   |
| Console commands   | `tests/Feature/Console/Commands/`                         | `health:record` persistence                                                                                                                                                                                                           |
| Authorisation      | `tests/Feature/Authorization/`, `tests/Feature/Policies/` | Every Policy decision path and role matrix row                                                                                                                                                                                        |
| Listeners          | `tests/Feature/Listeners/`                                | Exactly-once audit persistence and OTP dispatch                                                                                                                                                                                       |
| Models and Support | `tests/Feature/Models/`, `tests/Feature/Support/`         | Scopes, casts, envelope helpers                                                                                                                                                                                                       |
| Query layer        | `tests/Unit/Queries/`                                     | Sort, filter, include, and sparse-fieldset state - no database                                                                                                                                                                        |
| Services and DTOs  | `tests/Unit/Services/`, `tests/Unit/DataTransferObjects/` | User-agent parser, health checks and registry, permission catalogue, CSP report parser                                                                                                                                                |
| Support            | `tests/Unit/Support/`                                     | Parse grammar, E.164 phones, input bounds                                                                                                                                                                                             |
| Notifications      | `tests/Unit/Notifications/`                               | Reset-link, password-changed, verification, and two-factor mail bodies, config-driven destinations                                                                                                                                    |
| Resources          | `tests/Unit/Http/Resources/`                              | Sparse fieldsets and serialisation branches                                                                                                                                                                                           |
| Rules              | `tests/Unit/Rules/`                                       | Custom validation rules against hostile input                                                                                                                                                                                         |
| Providers          | `tests/Unit/Providers/`                                   | Default password policy                                                                                                                                                                                                               |

## Layout

```text
tests/
├── Concerns/             # AssertsApiEnvelope, MakesStatefulSpaRequests, FakesBreachLookup, BuildsGeoLiteCityDatabase
├── Feature/
│   ├── Actions/          # Action units against the real database (auth, sessions, tokens, users, clients)
│   ├── Authorization/    # Gate and role-matrix checks
│   ├── Console/Commands/ # Scheduled command behaviour (health:record)
│   ├── Http/Controllers/ # Endpoint tests mirroring routes/api.php
│   ├── Http/*.php        # Cross-cutting HTTP tests (CORS, docs, security probes, rate-limit key safety)
│   ├── Http/Middleware/  # Gate middleware in isolation
│   ├── Listeners/        # Queued listener behaviour (audit, OTP dispatch)
│   ├── Models/           # Model scopes, casts, and helpers
│   ├── Policies/         # Policy decision paths
│   ├── Services/         # Permission catalogue
│   └── Support/          # Envelope and auth support helpers
└── Unit/
    ├── Actions/          # Token and auth units, no database
    ├── Config/           # .env.example parity checks
    ├── DataTransferObjects/ # System health result DTO
    ├── Http/Resources/   # Sparse fieldsets and serialisation branches
    ├── Notifications/    # Mail message bodies
    ├── Providers/        # Default password policy
    ├── Queries/          # Query builder state per resource
    ├── Rules/            # Custom validation rules
    ├── Services/         # User-agent parser, health checks and registry, permission catalogue
    └── Support/          # Parsers, E.164 phones, input bounds, security headers
```

`tests/phpstan/` holds helper bootstrap code for the static-analysis setup, not
tests.

[scripts/pen-test-auth.sh](../scripts/pen-test-auth.sh) drives live HTTP probes
against a running Sail stack: account enumeration, SQLi-shaped input, rate
limits, bearer and reset-token abuse, replay, remember-me and CSRF boundaries,
suspension and soft-delete handling, web-session IDOR, credential rotation on
password reset, session-activity tracking, email verification, team management
boundaries, role and phone hardening, session show/revoke-others, CSP report
abuse, security.txt, webhook management, client secret rotation, live role and
ability drift (scoped PATs, admin-issued tokens, machine-token revocation),
credential lifecycle edges (reset broker vs role change, deactivation, ability
reorder), retired flat auth paths, and comma-separated list filters (any-of
semantics, per-filter caps, non-canonical key forms, allow-list hints,
per-value rejection, and authorising scope). 51 sections print `PASS` /
`FAIL` / `WARN` lines and the script exits non-zero on any failure.

```bash
./vendor/bin/sail artisan migrate:fresh --seed
bash scripts/pen-test-auth.sh
```

> [!IMPORTANT]
> `migrate:fresh --seed` wipes the local database.

### One Probe Is Expected to Flake

**Section 23, `Timing Side-Channel (Rough)`, is a single-sample measurement and
will intermittently report `FAIL` or `WARN`.** It times one login attempt for an
unknown User against one with a wrong password, then compares the two with a 3x
threshold:

```
INFO  Timing ratio unknown/wrong: 4.11x (u=0.412s w=0.100s)
WARN  Timing side-channel - ratio > 3x may aid enumeration
```

On a cold container, a first request paying autoload cost against a warm one, or
another process competing for CPU, is enough to trip that ratio. It is a load
artefact, not a regression: re-run the script and it usually passes, and a
sustained ratio _is_ worth investigating.

The right fix is for the probe to take the median of several samples per side
rather than one, which would make it a reliable signal instead of noise. Until
then, **treat an isolated section 23 failure as inconclusive and re-run** - and
do not "fix" it by widening the threshold, which would hide the real case.

The stateful probes (remember-me cookies, CSRF, session-activity touch) need
`SESSION_DRIVER=redis` and `CACHE_STORE=redis` in `.env`. With the `array`
driver the script detects the downgrade and exercises the registry through
tinker-seeded rows instead, warning where a cookie flow could not be exercised.

> [!CAUTION]
> The script creates and mutates accounts and must only run against `local`.

## Coverage Floor

Step 4 enforces 90% line coverage over `app/`, measured by
[check-coverage-threshold.php](../scripts/check-coverage-threshold.php). The
only exclusion is `TelescopeServiceProvider.php` (published vendor scaffolding,
never booted in `testing`). Note that `composer test` does not refresh the
coverage artefact - run `test:coverage:check` (or `test:coverage`) before
reading the percentage. When new code lands without covering its rejection
paths, the gate fails; that is the intended friction.

### `#[CoversClass]` On a Trait Fails the Coverage Run

**A plain suite run will be green while the coverage run fails.** PHPUnit
reports `OK, but there were issues!` followed by one warning per affected test,
and exits non-zero, so `test:coverage:check` cannot pass:

```
OK, but there were issues!
Tests: 1394, Assertions: 5613, PHPUnit Warnings: 301.

301 tests triggered 301 PHPUnit warnings:
  Class App\Http\Requests\Concerns\AppliesDateRangeFilters is not a valid target
  for code coverage
```

**Why.** PHPUnit maps each `#[CoversClass]` to a coverage _target_. A trait is
not a resolvable target for that attribute - it needs `#[CoversTrait]` - so
`Mapper::mapTarget()` throws and PHPUnit downgrades it to a warning. The
attribution for every **other** class listed in the same file collapses with it,
which is why unrelated DTOs and Actions then report `0%` on classes the suite
plainly executes. The report is mis-attributed, not truncated, and the file list
looks implausible precisely because the runs are otherwise green.

**The fix is to drop the attribute.** On a feature test, list the classes the
HTTP path actually runs - the controller, its FormRequest, the DTO, the Resource,
the Policy, the Query classes - and do not name the concern:

```php
// incorrect - a trait is not a target for CoversClass
#[CoversClass(AppliesDateRangeFilters::class)]
#[CoversClass(UserIndexController::class)]

// correct - every real class on the path, no trait
#[CoversClass(UserIndexController::class)]
#[CoversClass(UserIndexRequest::class)]
#[CoversClass(UserFilterQuery::class)]
#[CoversClass(UserFilters::class)]
```

Interfaces have the same constraint and the same fix.

**How to spot it in review.** Coverage is per-run, so a plain `composer test`
will not show this. Read the coverage run's summary line, not just its exit code:
`PHPUnit Warnings: N` with `N > 0` is a failure even when the tests all pass.
When auditing coverage that looks wrongly low, check for
`is not a valid target for code coverage` in the output before suspecting PCOV,
Xdebug, or the container.

### The Coverage Run Can Exceed Composer's 300-Second Timeout

`composer test:coverage` is a nested Composer script, so Composer's default
`process-timeout` of 300 seconds applies to it. A suite that takes ~200 seconds
plain can exceed 300 under coverage, and Composer then kills the child process:

```
The process "'/usr/bin/php8.5' ... '/usr/bin/composer' test:coverage" exceeded
the timeout of 300 seconds.
```

**This looks exactly like a coverage bug and is not one.** The killed run leaves
a partial `storage/coverage/clover.xml`, and two runs are byte-identical because
they die at the same point. The tell is the wording: a genuine coverage failure
reports `Tests: N failed` or a threshold message, whereas a timeout reports
`exceeded the timeout of N seconds`.

Check the reported duration against the timeout before investigating the driver.
If the suite has grown past the limit, raise `process-timeout` in
[composer.json](../composer.json) or cut the runtime, so a slow run does not fail
for a reason unrelated to what the gate measures.

## Documentation Sync

After changing an endpoint: update [openapi.yaml](openapi.yaml), re-run step 5,
and check [README.md](../README.md) and [permissions.md](permissions.md) still
match. Release notes go to [CHANGELOG.md](../CHANGELOG.md) under
`[Unreleased]`.
