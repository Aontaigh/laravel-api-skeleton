#!/usr/bin/env bash

# -----------------------------------------------------------------------------
# Dockerfile Lint (hadolint)
#
# Lints the image the local stack is built from, `docker/8.5/Dockerfile`. The CI
# `hadolint` job calls this script, so the image pin, the failure threshold and
# the target live in one place and cannot drift apart.
#
# `--failure-threshold warning` keeps the gate where the workflow set it:
# warnings and errors fail, the informational hints (consecutive `RUN`s, a
# missing `USER`) print without blocking. `--workdir /work` is what puts
# `.hadolint.yaml`, at the repository root, in hadolint's search path; that file
# names the exceptions this Dockerfile deliberately takes, each with its reason.
#
# Docker-backed: the engine is a pinned container image, so this needs a running
# daemon. There is no host-install fallback, and a daemon that is missing or
# stopped says so rather than reporting a clean lint of nothing.
#
# Usage: bash scripts/run-hadolint.sh
# -----------------------------------------------------------------------------

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

HADOLINT_IMAGE="${HADOLINT_IMAGE:-hadolint/hadolint:v2.15.1-debian@sha256:9a3944b7fddcb947d1ffd90829ac1a6e5c30479223358f249d8b96c7d0019e27}"

# -----------------------------------------------------------------------------
# Docker
# -----------------------------------------------------------------------------

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker Is Not Installed: this check runs hadolint from a pinned image" >&2
    echo "    image: $HADOLINT_IMAGE" >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker Daemon Is Not Reachable: start Docker and re-run this script" >&2
    echo "    image: $HADOLINT_IMAGE" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# Lint
# -----------------------------------------------------------------------------

docker run --rm \
    --workdir /work \
    --volume "$PWD:/work:ro" \
    "$HADOLINT_IMAGE" \
    hadolint --no-color --failure-threshold warning /work/docker/8.5/Dockerfile

echo "Hadolint Passed (docker/8.5/Dockerfile)"
