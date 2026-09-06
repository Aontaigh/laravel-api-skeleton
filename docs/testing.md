# Testing

The full suite of checks to run before opening a pull request: PHPUnit suites,
the coverage floor, static analysis, OpenAPI example verification, Semgrep, and
the adversarial auth pen test. Run everything through Sail so the PHP version
matches CI; CI ([.github/workflows/ci.yml](../.github/workflows/ci.yml)) runs
the same gates as parallel jobs behind one **All Quality Gates** check.

## Prerequisites

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan migrate:fresh --seed   # fresh state for the pen test
```

The unit suite runs with `QUEUE_CONNECTION=sync` (set in `phpunit.xml`), so
queued listeners (audit writes, notifications) execute inline and are
assertable without a worker.

## Quality Gates

Run in this order; stop at the first failure.

| Step | Command | Checks | Pass condition |
| --- | --- | --- | --- |
| 1 | `./vendor/bin/sail composer lint` | Pint style check (`lint:fix` to auto-fix) | Exit 0 |
| 2 | `./vendor/bin/sail composer analyse` | Larastan at level 9 with strict, deprecation, and PHPUnit rules ([phpstan.neon](phpstan.neon)) | `No errors` |
| 3 | `./vendor/bin/sail composer test` | Full PHPUnit suite (unit + feature, ~800 tests) | All pass |
| 4 | `./vendor/bin/sail composer test:coverage:check` | Step 3 plus the 90% line-coverage gate over `app/` | `Coverage Gate Passed` |
| 5 | `./vendor/bin/sail composer verify:openapi` | Replays every example in [openapi.yaml](openapi.yaml) against the live app | `All OpenAPI Examples Verified` |
| 6 | `bash scripts/semgrep.sh` | SAST with Laravel security rules (run on the host, not in Sail) | `Findings: 0` |
| 7 | `bash scripts/pen-test-auth.sh` | Live adversarial probes (see below) | `Fail: 0` |

`composer ci` chains lint, analyse, coverage, and `composer audit`; the OpenAPI
and Semgrep checks run as separate CI jobs. Do not add baseline entries to
silence new static-analysis findings - the typed accessors and narrowed
properties the codebase uses exist to keep level 9 green.

> [!NOTE]
> `verify:openapi` needs a running app with a seeded database. Its admin login
> queues an audit write - with `QUEUE_CONNECTION=redis`, drain the queue once
> before verifying:
> `./vendor/bin/sail artisan queue:work --stop-when-empty`

## Test Suites

Two suites, one rule each: **unit tests never touch the database, feature tests
always do.**

| Suite | Base class | Database | Covers |
| --- | --- | --- | --- |
| `tests/Unit/` | [UnitTestCase](../tests/UnitTestCase.php) | Forbidden - any query fails the test at teardown | Query builders, Support parsers, DTOs, checks, notification mail bodies |
| `tests/Feature/` | [TestCase](../tests/TestCase.php) with `RefreshDatabase` | Real MySQL (`testing` database) | HTTP endpoints, Actions, middleware, console commands, queued listeners |

## What the Suite Covers

| Area | Where | What is exercised |
| --- | --- | --- |
| HTTP endpoints | `tests/Feature/Http/Controllers/` | Every route in [routes/api.php](../routes/api.php): auth, two-factor, password reset, sessions, users, tokens, API clients, audit logs, roles, permissions, teams, system status |
| Actions | `tests/Feature/Actions/` | Registration, credential finalisation, token creation, session revocation, password change, user admin - against the real database |
| Middleware | `tests/Feature/Http/Middleware/` | Session-version gate, account-active gate, session-activity touch, security headers |
| Console commands | `tests/Feature/Console/Commands/` | `health:record` persistence |
| Authorisation | `tests/Feature/Authorization/`, `tests/Feature/Policies/` | Every Policy decision path and role matrix row |
| Listeners | `tests/Feature/Listeners/` | Exactly-once audit persistence and OTP dispatch |
| Models and Support | `tests/Feature/Models/`, `tests/Feature/Support/` | Scopes, casts, envelope helpers |
| Query layer | `tests/Unit/Queries/` | Sort, filter, include, and sparse-fieldset state - no database |
| Services and DTOs | `tests/Unit/Services/`, `tests/Unit/DataTransferObjects/` | User-agent parser, health checks and registry, permission catalog |
| Notifications | `tests/Unit/Notifications/` | Reset-link and password-changed mail bodies, config-driven destinations |
| Resources | `tests/Unit/Http/Resources/` | Sparse fieldsets and serialisation branches |

## Layout

```text
tests/
├── Concerns/             # AssertsApiEnvelope, MakesStatefulSpaRequests
├── Feature/
│   ├── Actions/          # Action units against the real database (auth, sessions, tokens, users, clients)
│   ├── Authorization/    # Gate and role-matrix checks
│   ├── Console/Commands/ # Scheduled command behaviour (health:record)
│   ├── Http/Controllers/ # Endpoint tests mirroring routes/api.php
│   ├── Http/Middleware/  # Gate middleware in isolation
│   ├── Listeners/        # Queued listener behaviour (audit, OTP dispatch)
│   ├── Models/           # Model scopes, casts, and helpers
│   ├── Policies/         # Policy decision paths
│   ├── Services/         # Permission catalog
│   └── Support/          # Envelope and auth support helpers
└── Unit/
    ├── Actions/          # Token and auth units, no database
    ├── DataTransferObjects/ # System health result DTO
    ├── Http/Resources/   # Sparse fieldsets and serialisation branches
    ├── Notifications/    # Mail message bodies
    ├── Providers/        # Default password policy
    ├── Queries/          # Query builder state per resource
    └── Services/         # User-agent parser, health checks and registry, permission catalog
```

`tests/phpstan/` holds helper bootstrap code for the static-analysis setup, not
tests.

[scripts/pen-test-auth.sh](../scripts/pen-test-auth.sh) drives live HTTP probes
against a running Sail stack: account enumeration, SQLi-shaped input, rate
limits, bearer and reset-token abuse, replay, remember-me and CSRF boundaries,
suspension and soft-delete handling, web-session IDOR, credential rotation on
password reset, and session-activity tracking. 41 sections print `PASS` /
`FAIL` / `WARN` lines and the script exits non-zero on any failure.

```bash
./vendor/bin/sail artisan migrate:fresh --seed
bash scripts/pen-test-auth.sh
```

> [!IMPORTANT]
> `migrate:fresh --seed` wipes the local database.

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

## Documentation Sync

After changing an endpoint: update [openapi.yaml](openapi.yaml), re-run step 5,
and check [README.md](../README.md) and [permissions.md](permissions.md) still
match. Release notes go to [CHANGELOG.md](../CHANGELOG.md) under
`[Unreleased]`.
