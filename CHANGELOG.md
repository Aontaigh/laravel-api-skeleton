# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-08-06

### Added

- `ListSortParser` and `AllowList` Support helpers for query-param parsing and allow-list comparison
- Unit tests for `ListSortParser` and `AllowList` with adversarial `#[DataProvider]` coverage
- CORS configuration (`config/cors.php`) and `ApiCorsTest` feature coverage for browser clients
- Personal Access Token expiration via `API_TOKEN_EXPIRATION_DAYS` (default 90), synced to Sanctum config
- Feature tests for expired Sanctum tokens and token `expires_at` on creation

### Changed

- FormRequest parse traits delegate grammar to Support classes; traits retain HTTP wiring only
- Removed redundant FormRequest harness unit tests — fields, include, and sort wiring covered by feature tests
- OpenAPI `TokensIndexSuccess` example uses nullable `last_used_at` for newly issued tokens
- OpenAPI verify script pre-creates index tokens and requests `sort=-id` for stable envelopes

### Fixed

- PHPStan types in `StoreTokenControllerTest` for token expiration assertions

## [1.1.0] - 2026-08-06

### Added

- Unit tests for API Resources (`UserResource`, `RoleResource`, `PermissionResource`, `TeamResource`, `PersonalAccessTokenResource`) and `SerialisesSparseAttributes`
- Unit tests for FormRequest concerns (`ParsesFieldsQueryParam`, `ParsesIncludeQueryParam`, `ParsesSearchQueryParam`, `SanitisesPlainTextAttributes`, `ValidatesTokenPayload`)
- Unit tests for Support helpers (`CommaSeparatedList`, `ApiExceptionRenderer`, `ApiDateTime`, `AllowListValidation`, `ApiResponse`, `LikePattern`, `PlainText`, `QualifiedColumn`)
- Feature tests for Sanctum authentication, API token rate limiting, and security probes (SQL injection, mass assignment, boundary inputs)
- Feature tests for `UserPolicy` edge cases and expanded controller coverage
- CI job verifying OpenAPI component examples against live API responses (`composer verify:openapi`)
- `fields[roles]` sparse-fieldset validation on `UserIndexRequest` and matching invalid-query coverage

### Changed

- FormRequest concerns aligned with `ApiFormRequest` base and ai-rules conventions (`ParsesSearchQueryParam` `@mixin`, dash-underline test groups, PHPDoc on every `#[Test]`)
- Test suite reorganised: removed `#[CoversTrait]` from feature tests; concern and resource tests use dedicated unit namespaces
- `scripts/verify-openapi-examples.sh` supports CI via `ARTISAN_CMD` and `OPENAPI_VERIFY_BASE` environment variables

## [1.0.0] - 2026-08-06

### Added

- Laravel 13 API skeleton with Sanctum bearer authentication and Spatie roles and permissions
- Users, Roles, and Tokens resources with a consistent query-driven index pattern
- OpenAPI specification, permissions reference, and performance notes
- CI quality gates: Pint, Larastan level 9, PHPUnit with 90% line-coverage gate, and `composer audit`
- Laravel Sail setup with MySQL and Redis for local development

[Unreleased]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/Aontaigh/laravel-api-skeleton/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Aontaigh/laravel-api-skeleton/releases/tag/v1.0.0
