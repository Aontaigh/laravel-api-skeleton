#!/usr/bin/env bash
# Static application security testing for PHP and CI YAML.
# Host (Docker) or a local Semgrep CLI. Not installed inside Sail.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Pin so CI and laptops run the same engine. Override with SEMGREP_IMAGE if needed.
SEMGREP_IMAGE="${SEMGREP_IMAGE:-semgrep/semgrep:1.173.0}"

# Community packs: language rules plus the OWASP / CWE / secrets / PHP audit
# sets that Semgrep and Trail of Bits recommend for a blocking web-app gate.
# Laravel rules use r/php.laravel.security (p/laravel is not published — HTTP 404).
# p/trailofbits is omitted: it flags Sail binding 0.0.0.0, which is required
# for host access to the app container.
CONFIGS=(
    --config p/php
    --config r/php.laravel.security
    --config p/phpcs-security-audit
    --config p/owasp-top-ten
    --config p/cwe-top-25
    --config p/secrets
    --config p/security-audit
    --config p/github-actions
)

# SHA-pinning every Actions tag is a separate supply-chain project (OpenSSF
# Scorecard). Dependabot already bumps action major tags weekly. Keep the rest
# of the GitHub Actions pack.
EXCLUDE_RULES=(
    --exclude-rule yaml.github-actions.security.github-actions-mutable-action-tag.github-actions-mutable-action-tag
)

# --error exits 1 on any finding. --strict is omitted: Semgrep cannot fully
# parse GitHub workflow expressions and would exit 3 on a parse warning.
ARGS=(scan "${CONFIGS[@]}" "${EXCLUDE_RULES[@]}" --metrics=off --error "$@")

run_semgrep() {
    if command -v semgrep >/dev/null 2>&1; then
        exec semgrep "${ARGS[@]}"
    fi

    if command -v docker >/dev/null 2>&1; then
        exec docker run --rm \
            -v "${ROOT}:/src" \
            -w /src \
            "${SEMGREP_IMAGE}" \
            semgrep "${ARGS[@]}"
    fi

    echo "Semgrep Is Not Installed: install the CLI, or Docker, then run ./scripts/semgrep.sh" >&2
    exit 1
}

run_semgrep
