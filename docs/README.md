# Documentation

Start with the [repository README](../README.md) for orientation and the API
overview. This directory holds the deeper guides, grouped by purpose following
the [Diátaxis](https://diataxis.fr/) framework: tutorials for learning, how-to
guides for tasks, reference for facts, and explanation for understanding.

Previous flat paths (`docs/api.md`, `docs/testing.md`, and the rest) remain as
one-line redirect stubs for one release cycle; update links to the category paths.
`docs/openapi.yaml` is a comment-only stub, so OpenAPI import tools must use
`docs/reference/openapi.yaml`.

## Tutorials

- [Local development](tutorials/local-development.md) - clone the repo, build the
  Sail stack, seed the database, and issue a token.

## How-to

- [OpenAPI and Scalar](how-to/api-docs.md) - Scalar, OpenAPI preview, and client import.
- [Testing](how-to/testing.md) - quality gates, test suites, and the auth pen test.
- [Releasing](how-to/releasing.md) - cut a release from a green commit.
- [Branch hygiene](how-to/branch-hygiene.md) - branch, pull request, and tag cleanup.

## Reference

- [OpenAPI spec](reference/openapi.yaml) - the machine-readable contract served at
  `/api/openapi.yaml`.
- [Permissions](reference/permissions.md) - role matrix and permission strings.
- [Rate limiting](reference/rate-limiting.md) - limits, keys, and IPv6 handling.

## Explanation

- [Performance](explanation/performance.md) - costs and limits of the query-driven
  index pattern.
- [Security engineering standards](explanation/security-engineering-standards.md) -
  controls, boundaries, and review rules.
- [Platform assurance](explanation/platform-assurance.md) - measured evidence behind
  the security and quality claims.

## Contributing

- [CONTRIBUTING.md](../CONTRIBUTING.md) - setup, workflow, standards, and PR checklist.
- [SECURITY.md](../SECURITY.md) - how to report a vulnerability.
- [CHANGELOG.md](../CHANGELOG.md) - released changes.
