#!/usr/bin/env bash

# -----------------------------------------------------------------------------
# Shell Check
#
# Runs ShellCheck over every tracked shell script, selected by extension or by
# shebang, so a new script joins the gate by being committed with or without the
# `.sh` extension. The CI `shellcheck` job calls this script, so the image pin,
# the severity and the selection rule live in one place and cannot drift apart.
#
# Usage: bash scripts/run-shellcheck.sh
# -----------------------------------------------------------------------------

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SHELLCHECK_IMAGE="${SHELLCHECK_IMAGE:-koalaman/shellcheck:v0.11.0@sha256:61862eba1fcf09a484ebcc6feea46f1782532571a34ed51fedf90dd25f925a8d}"

# -----------------------------------------------------------------------------
# Selection
# -----------------------------------------------------------------------------

# Extension or shebang: `git ls-files '*.sh'` alone would miss a tracked
# entrypoint that is bash with no `.sh` in its name. The alternation is
# `(bash|sh)` because a bare `sh` does not match `#!/usr/bin/env bash`: `\b`
# finds no word boundary between the `a` and the `s` of `bash`.
#
# The accumulator and not `mapfile`, which is a bash 4 builtin: macOS ships bash
# 3.2. Reading the here-string rather than a pipeline is what keeps the loop in
# this shell, so the array survives.
#
# `git ls-files` is captured rather than streamed through a process substitution,
# whose exit status strict mode cannot see: a Git failure would leave the loop
# with nothing to read, and the empty-list guard below would then report a clean
# scan of nothing.
files=$(git ls-files)

scripts=()
while IFS= read -r file; do
    case "$file" in
        *.sh) scripts+=("$file") ;;
        *) if [ -f "$file" ] && head -n 1 "$file" | grep -qE '^#!.*\b(bash|sh)\b'; then scripts+=("$file"); fi ;;
    esac
done <<< "$files"

# A repository with no scripts in scope says so rather than reporting a clean
# scan of nothing.
if [ "${#scripts[@]}" -eq 0 ]; then
    echo "No Shell Scripts Found To Lint"
    exit 0
fi

# -----------------------------------------------------------------------------
# Lint
# -----------------------------------------------------------------------------

printf 'Linting %d Shell Script(s)\n' "${#scripts[@]}"

docker run --rm \
    --workdir /work \
    --volume "$PWD:/work:ro" \
    "$SHELLCHECK_IMAGE" \
    --severity=warning "${scripts[@]}"

echo "Shell Lint Passed (${#scripts[@]} File(s))"
