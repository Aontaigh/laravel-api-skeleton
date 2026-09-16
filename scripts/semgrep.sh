#!/bin/bash
#
# Static application security testing with Semgrep.
#
# Scans PHP, JavaScript/TypeScript, and CI YAML against the community packs plus
# the repo's own rules, and exits non-zero on any finding. Runs a local Semgrep
# CLI when present, otherwise the digest-pinned engine image.
#
# Usage:
#   scripts/semgrep.sh [semgrep args...]
#
# Environment:
#   SEMGREP_IMAGE  override the pinned engine image
#
set -euo pipefail

# ---------------------------------------------------------------------------
# Setup
# ---------------------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd -P)"
cd "${REPO_ROOT}"

# Pin the engine by digest so CI and laptops run the same Semgrep. Override
# with SEMGREP_IMAGE when testing a newer engine.
SEMGREP_IMAGE="${SEMGREP_IMAGE:-semgrep/semgrep@sha256:acaac22ffc7b7cc5926de0751b223bce0b2491c33d18422fa72f632c78d81198}" # 1.177.0

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

# run_semgrep : exec the engine with the assembled ARGS (local CLI, else Docker)
run_semgrep() {
    if command -v semgrep >/dev/null 2>&1; then
        exec semgrep "${ARGS[@]}"
    fi

    if command -v docker >/dev/null 2>&1; then
        exec docker run --rm \
            -v "${REPO_ROOT}:/src" \
            -w /src \
            "${SEMGREP_IMAGE}" \
            semgrep "${ARGS[@]}"
    fi

    echo "Semgrep Is Not Installed: install the Semgrep CLI or Docker, then rerun scripts/semgrep.sh" >&2
    exit 1
}

# ---------------------------------------------------------------------------
# Work
# ---------------------------------------------------------------------------

# Community packs: language rules plus the OWASP / CWE / secrets / supply-chain
# sets Semgrep recommends for a blocking web-app gate. Laravel rules use
# r/php.laravel.security (p/laravel is not published - HTTP 404). p/trailofbits
# is omitted: it flags Sail binding 0.0.0.0, required for host access to the app.
# `semgrep/config.yml` adds the repo's own rules.
CONFIGS=(
    --config p/php
    --config r/php.laravel.security
    --config p/javascript
    --config p/typescript
    --config p/phpcs-security-audit
    --config p/owasp-top-ten
    --config p/cwe-top-25
    --config p/secrets
    --config p/security-audit
    --config p/github-actions
    --config semgrep/config.yml
)

# SHA-pinning every Actions tag is a separate supply-chain project (OpenSSF
# Scorecard). Dependabot bumps action major tags weekly. Keep the rest of the pack.
EXCLUDE_RULES=(
    --exclude-rule yaml.github-actions.security.github-actions-mutable-action-tag.github-actions-mutable-action-tag
)

# --error exits non-zero on any finding. --strict is omitted: Semgrep cannot
# fully parse GitHub workflow expressions and would exit 3 on a parse warning.
ARGS=(scan "${CONFIGS[@]}" "${EXCLUDE_RULES[@]}" --metrics=off --error "$@")

run_semgrep
