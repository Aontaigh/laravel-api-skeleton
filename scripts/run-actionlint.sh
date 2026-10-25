#!/usr/bin/env bash

# -----------------------------------------------------------------------------
# Workflow Lint (actionlint)
#
# Lints every workflow under `.github/workflows/`. The CI `workflow-lint` job
# calls this script, so the image pin and the scope live in one place and cannot
# drift apart.
#
# `--workdir /repo` is the repository root inside the container, and it is what
# actionlint discovers `.github/workflows` from: a run whose working directory
# moved would silently lint nothing. `.github/actionlint.yaml` allow-lists the
# runner labels this repository resolves through `vars`, and actionlint reads it
# from the same relative path automatically.
#
# Docker-backed: the engine is a pinned container image, so this needs a running
# daemon. There is no host-install fallback, and a daemon that is missing or
# stopped says so rather than reporting a clean lint of nothing.
#
# Usage: bash scripts/run-actionlint.sh
# -----------------------------------------------------------------------------

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

ACTIONLINT_IMAGE="${ACTIONLINT_IMAGE:-rhysd/actionlint:1.7.12@sha256:b1934ee5f1c509618f2508e6eb47ee0d3520686341fec936f3b79331f9315667}"

# -----------------------------------------------------------------------------
# Docker
# -----------------------------------------------------------------------------

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker Is Not Installed: this check runs actionlint from a pinned image" >&2
    echo "    image: $ACTIONLINT_IMAGE" >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker Daemon Is Not Reachable: start Docker and re-run this script" >&2
    echo "    image: $ACTIONLINT_IMAGE" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# Lint
# -----------------------------------------------------------------------------

docker run --rm \
    --volume "$PWD:/repo:ro" \
    --workdir /repo \
    "$ACTIONLINT_IMAGE"

echo "Actionlint Passed (GitHub Workflows)"
