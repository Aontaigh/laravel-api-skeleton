# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-06

### Added

- Laravel 13 API skeleton with Sanctum bearer authentication and Spatie roles and permissions
- Users, Roles, and Tokens resources with a consistent query-driven index pattern
- OpenAPI specification, permissions reference, and performance notes
- CI quality gates: Pint, Larastan level 9, PHPUnit with 90% line-coverage gate, and `composer audit`
- Laravel Sail setup with MySQL and Redis for local development
