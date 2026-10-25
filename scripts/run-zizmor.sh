#!/usr/bin/env bash

# -----------------------------------------------------------------------------
# Workflow Audit (zizmor)
#
# Audits every workflow under `.github/workflows/` against the repository's
# hash-pinning policy in `.github/zizmor.yml`. The CI `zizmor` job calls this
# script for the blocking audit, so the image pin, the config and the scope live
# in one place and cannot drift apart.
#
# The severity is left at zizmor's default, exactly as the gate runs it: this
# script mirrors the merge gate, it does not tighten it. The SARIF re-run in the
# workflow stays its own inline `docker run`, because it reports for code
# scanning rather than blocking on a finding.
#
# `--workdir /repo` plus the absolute config and target paths are the invocation
# the `zizmor` job used inline, kept verbatim so the local run and the gate
# resolve the same files.
#
# Docker-backed: the engine is a pinned container image, so this needs a running
# daemon. There is no host-install fallback, and a daemon that is missing or
# stopped says so rather than reporting a clean audit of nothing.
#
# Usage: bash scripts/run-zizmor.sh
# -----------------------------------------------------------------------------

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

ZIZMOR_IMAGE="${ZIZMOR_IMAGE:-ghcr.io/zizmorcore/zizmor:1.31.1@sha256:42d409dbb851d4c5d35a058cf042b828bea1e3f2884378c526eb0974780e18d9}"

# -----------------------------------------------------------------------------
# Docker
# -----------------------------------------------------------------------------

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker Is Not Installed: this check runs zizmor from a pinned image" >&2
    echo "    image: $ZIZMOR_IMAGE" >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker Daemon Is Not Reachable: start Docker and re-run this script" >&2
    echo "    image: $ZIZMOR_IMAGE" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# Audit
# -----------------------------------------------------------------------------

docker run --rm \
    --volume "$PWD:/repo:ro" \
    --workdir /repo \
    "$ZIZMOR_IMAGE" \
    --config /repo/.github/zizmor.yml /repo/.github/workflows/

echo "Zizmor Passed (GitHub Workflows)"
