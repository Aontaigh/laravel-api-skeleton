# Contributing

This repository is a Laravel API starter. Keep changes small, tested, and
documented. The [documentation index](docs/README.md) is the map.

## Before You Start

- Read the [README](README.md) for the architecture and API contract.
- Read [docs/README.md](docs/README.md) for the guide that matches your task.
- Report security vulnerabilities through [SECURITY.md](SECURITY.md), not a public
  issue.

## Local Setup

Follow [docs/tutorials/local-development.md](docs/tutorials/local-development.md).
It covers the Docker bootstrap, Sail, the seeded accounts, and the token flow.

Run the quality gates from [docs/how-to/testing.md](docs/how-to/testing.md) before
opening a pull request. The short version:

```bash
./vendor/bin/sail composer lint
./vendor/bin/sail composer analyse
./vendor/bin/sail composer test
```

## Workflow

- `main` is the only trunk. There is no `develop`, release branch, or environment
  branch.
- Use a short-lived branch: `feat/*`, `fix/*`, `docs/*`, `chore/*`, or `test/*`.
- Open one pull request per logical change. Link the issue it closes.
- Keep the pull request description focused on the problem, the change, and the
  verification you ran.
- Do not force-push `main`. Do not push to `dependabot/*` branches.
- Delete the branch after merge. See
  [docs/how-to/branch-hygiene.md](docs/how-to/branch-hygiene.md).

## Standards

- PHP: Pint formatting, Larastan level 10, and the PHPUnit coverage floor. See
  [docs/how-to/testing.md](docs/how-to/testing.md).
- API changes: update [docs/reference/openapi.yaml](docs/reference/openapi.yaml) and
  the affected guides in the same change. The spec is the contract.
- Documentation: the `docs/` tree follows [Diátaxis](https://diataxis.fr/)
  (`tutorials/`, `how-to/`, `reference/`, `explanation/`). Put a new page in the
  category that matches its purpose, add it to [docs/README.md](docs/README.md),
  and keep links resolving with `./vendor/bin/sail composer lint:links`. Link to
  the canonical category page, not the flat redirect stubs in `docs/`.
- Changelog: add user-visible changes under `Unreleased` in
  [CHANGELOG.md](CHANGELOG.md).
- Commits: imperative subject, explain the reason in the body when the change is
  not obvious.

## Pull Request Checklist

- [ ] The change is scoped to one problem.
- [ ] New behavior has a test that would fail without the change.
- [ ] `lint`, `analyse`, and the relevant test suite pass.
- [ ] OpenAPI and docs are updated when the API changes.
- [ ] No secrets, credentials, or local environment values are committed.
- [ ] The changelog entry is present for user-visible changes.

## Release Process

Releases are cut from a green `main` commit. Follow
[docs/how-to/releasing.md](docs/how-to/releasing.md). Do not create a release
branch or move a published tag.
