#!/bin/bash
#
# Check every relative link and heading anchor in this repository's Markdown.
#
# Canonical source: `kaxmedia/ai-rules` `scripts/lint-links.sh`. Consuming
# repositories hold a verbatim copy, so the **Configuration** block is the only
# section they should edit. When the canonical copy gains a fix, re-copy it and
# re-apply that block; never hand-patch the body. Three divergent copies of one
# gate is exactly how a repository ended up with a link checker that CI never ran.
#
# Usage:
#   scripts/lint-links.sh            # every link, including external (network, ~90s)
#   scripts/lint-links.sh FILE ...   # only the given files (used by a test suite)
#
# Why this exists: markdownlint's MD051 checks heading anchors, but this repo
# disables it repo-wide because GitHub strips emoji from heading slugs, so a dead
# anchor is invisible to that gate. This script resolves local paths and `#anchor`
# fragments instead, which is where the real breakage lives.
#
# Checks:
#   * Markdown under version control, including untracked new files
#   * skips generated output, which is a derivative of the source and is covered by
#     the drift check in `build.sh`
#
# Accumulates every dead link, then exits non-zero once at the end.
#
# There is deliberately no offline or local-only mode. markdown-link-check has no flag
# for one: every `https://` link is fetched regardless, so a "local only" run still
# pays for every external request and fails on transient network errors instead of
# dead links. The full pass is the honest cost of checking thousands of external
# URLs, so the gate runs in CI and on demand rather than in a tight edit loop. To
# check local links in under a second while editing, use the repo's own anchor scan
# or `markdownlint-cli2`, which resolves `#fragment` targets from the headings on disk.
#
# Passing explicit paths narrows the scan instead of changing what is checked per
# link. The test suite needs that: proving the gate still fails on a dead link means
# running it, and without scoping each of those proofs re-checks every URL in the
# repo, so five tests cost five full network passes instead of five milliseconds.
#
# Scope limits, stated so nobody expects more than it delivers:
#   * does NOT see `**Bold Anchor**` cross-references, only real markdown links
#   * only external links matching EXEMPT_LINK_PATTERNS are skipped, in three
#     classes: any `kaxmedia` URL (the toolkit is vendored into private repos, so
#     those 404 for a logged-out runner); the `.github/workflows/` placeholder that
#     skills use to mean "read this in the consuming repo"; and the reserved
#     placeholder domains plus authenticated hosts that appear inside template
#     examples (`example.com`, `gdcgroup.slack.com`), where the URL is sample
#     output rather than a real citation
#   * exempting is done here rather than through the tool's own `ignorePatterns`,
#     because setting that key makes markdown-link-check 3.15.0 stop reporting dead
#     links at all - it downgrades every failure to `[/]` and exits 0, which would
#     turn this gate into a no-op
#
# There is deliberately no `-c` config file. Two tool defaults are deliberately left
# alone, and both are recorded here because getting either wrong silently disables
# the gate rather than failing it:
#
#   * `ignorePatterns` must stay unset, for the reason above. On 3.15.0, any entry at
#     all - even one that matches nothing - suppresses dead-link reporting.
#   * `httpHeaders` is not set, so there is no User-Agent to get wrong. If one is ever
#     added, note that the key is an **array** of `{headers, urls}` entries, not a
#     plain object, and that headers apply only to links matching `urls`. An object
#     throws `TypeError: opts.httpHeaders is not iterable` on every file.
#
# A User-Agent was once suspected of turning a fifteen-minute scan into a one-second
# one. It did not survive an interleaved A/B: the same four files took 5s without the
# header and 37s with it, and repeated single-file runs came out at 1s either way. The
# original slow run was a transient GitHub 5xx storm, not a missing header. So no
# header is set, because none was shown to buy anything.

set -euo pipefail

# ---------------------------------------------------------------------------
# Setup
# ---------------------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd -P)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd -P)"

LINK_CHECK="$REPO_ROOT/node_modules/.bin/markdown-link-check"

if [ ! -x "$LINK_CHECK" ]; then
    echo "Error: markdown-link-check Is Not Installed: Run 'npm ci' First" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Configuration
#
# The only block a consuming repository edits. Everything below the Helpers
# divider is identical in every copy of this gate.
# ---------------------------------------------------------------------------

# Which files are candidates. These are git pathspecs, so `*.md` matches at any depth.
MARKDOWN_PATHS=(
    '*.md'
)

# Which candidates are skipped before the scan. Each entry is a regex matched
# against the repository-relative path. Use this for generated or vendored output:
# checking a derivative produces failures nobody can fix at the source.
EXCLUDED_PATH_PATTERNS=(
    # Third-party documentation is not ours to fix, and a dead link there would
    # fail this gate on every run with nothing anyone on the team can do about it.
    '^vendor/'
)

# Which external links are skipped. Every entry must fail for a reason other than
# being broken, and each needs the reason in the comment beside it.
EXEMPT_LINK_PATTERNS=(
    # Vendored into private repositories, so these 404 for a logged-out runner.
    'github\.com/kaxmedia'
    # Reserved placeholder domains used in template examples as sample output.
    '^https?://([a-z0-9-]+\.)*example\.(com|org|net)(/|$)'
    # Loopback URLs in the local-development docs. These are instructions, not
    # citations: README.md and docs/api.md send the reader to the docs page their
    # own Sail stack serves. The link resolves on a developer machine and is
    # unreachable on a runner, so checking it would fail CI for a page that is
    # correct.
    '^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?(/|$)'
    # Keep a Changelog footer for a version section that is not tagged yet: GitHub
    # compare URLs 404 until `vX.Y.Z` exists on the remote. Exempt only the
    # in-flight release compare (update or remove when v2.0.0 is published).
    'github\.com/Aontaigh/laravel-api-skeleton/compare/v1\.16\.1\.\.\.v2\.0\.0$'
)

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

# collect_targets <output-file> : write every checkable Markdown path, absolute, one per line
collect_targets() {
    local output="$1"
    local exclude_pattern
    local path

    # The patterns are joined into one alternation rather than chained `grep -v`
    # calls, so adding an exclusion stays a one-line change to the config block.
    # `${array[*]}` with `IFS` set is used instead of a nameref because macOS ships
    # bash 3.2, which has no `local -n`.
    exclude_pattern="$(IFS='|'; printf '%s' "${EXCLUDED_PATH_PATTERNS[*]}")"

    # `--others --exclude-standard` includes untracked files so a new page is checked
    # before it is ever committed; without it a fresh file passes silently.
    #
    # Paths are made absolute here because `xargs` inherits this script's working
    # directory and the tool throws ENOENT on a relative path it cannot stat - while
    # still exiting 0, which turns real breakage into a silent pass.
    #
    # The existence test drops paths that are in the index but gone from the working
    # tree, which is what `git stash` and an unstaged deletion leave behind. The tool
    # throws ENOENT on those, and the crash guard below would then fail the gate for a
    # file nobody deleted on purpose.
    #
    # Skipping is reported rather than silent, because the same test cannot tell an
    # intentional deletion from a committed symlink whose target was never committed.
    # The second case is broken for everyone and skips the file on every runner, so a
    # gate that counted only what it scanned would quietly under-report its own
    # coverage - which is worse than a noisy line. A dangling symlink gets its own
    # message because it is the actionable one; it is broken in a fresh clone, not
    # just in this working tree.
    while IFS= read -r path; do
        if [ -f "$path" ]; then
            printf '%s\n' "$path"
        elif [ -L "$path" ]; then
            printf 'Warning: Skipped Dangling Symlink (Target Not Committed): %s -> %s\n' \
                "${path#"$REPO_ROOT"/}" "$(readlink "$path")" >&2
        else
            printf 'Warning: Skipped Markdown File Missing From The Working Tree: %s\n' \
                "${path#"$REPO_ROOT"/}" >&2
        fi
    done < <(
        git -C "$REPO_ROOT" ls-files --cached --others --exclude-standard \
            "${MARKDOWN_PATHS[@]}" \
            | grep -vE "$exclude_pattern" \
            | sed "s|^|$REPO_ROOT/|"
    ) > "$output"
}

# collect_requested <output-file> <path>... : write only the given paths, absolute
collect_requested() {
    local output="$1"
    local requested

    shift

    for requested in "$@"; do
        # A missing or misspelled path is a caller bug, not a clean bill of health.
        # The tool stats its arguments and exits 0 when one cannot be read, so an
        # unchecked path would report a pass for a file nobody looked at.
        if [ ! -f "$requested" ]; then
            echo "Error: Link Lint File Not Found: $requested" >&2
            exit 1
        fi

        case "$requested" in
            *.md | *.mdc) ;;
            *)
                echo "Error: Link Lint Only Checks Markdown: $requested" >&2
                exit 1
                ;;
        esac

        printf '%s/%s\n' "$(cd "$(dirname "$requested")" && pwd -P)" "$(basename "$requested")" >> "$output"
    done
}

# is_exempt <link> : true when the link matches a pattern that is expected to fail
is_exempt() {
    local link="$1"
    local pattern

    for pattern in "${EXEMPT_LINK_PATTERNS[@]}"; do
        if printf '%s' "$link" | grep -qE "$pattern"; then
            return 0
        fi
    done

    return 1
}

# ---------------------------------------------------------------------------
# Work
# ---------------------------------------------------------------------------

TARGET_LIST="$(mktemp)"
trap 'rm -f "$TARGET_LIST"' EXIT

if [ "$#" -gt 0 ]; then
    collect_requested "$TARGET_LIST" "$@"
else
    collect_targets "$TARGET_LIST"
fi

file_count="$(wc -l < "$TARGET_LIST" | tr -d ' ')"

if [ "$file_count" -eq 0 ]; then
    echo "Error: Link Lint Found No Markdown Files To Check" >&2
    exit 1
fi

# `xargs -0` with a NUL-delimited stream keeps paths containing spaces intact.
#
# `xargs` reports 123 when the tool exits non-zero, which `set -e` reads as a crash,
# so the exit status is discarded and the report is parsed instead: the tool exits 1
# for dead links (expected when every one is exempt) and prints a crash signature
# when it genuinely could not run.
raw_report="$(tr '\n' '\0' < "$TARGET_LIST" | xargs -0 "$LINK_CHECK" 2>&1)" || true

# ---------------------------------------------------------------------------
# Verdicts
# ---------------------------------------------------------------------------

# The tool prints each failure twice, once bare and once with a `→ Status:` suffix, so
# the lines are collapsed onto the URL and emitted as `url<TAB>status`.
#
# This is a single awk pass with no `grep` in the pipeline on purpose. Under
# `set -o pipefail` a `grep` that matches nothing exits 1, and because the assignment
# `failures="$(...)"` inherits the pipeline's status, that turned a perfectly clean scan
# into a silent non-zero exit before a single verdict was formed.
#
# The split is on the literal `→ Status: ` marker rather than a second `sed`, because a
# pattern that also swallows the trailing space welds the status onto the URL:
# `https://x → Status: 0` became `https://x0`, a different address that resolves nowhere
# and so was retried as if it were a blip.
parse_failures() {
    printf '%s\n' "$1" | awk '
        BEGIN { marker_text = "→ Status: " }
        /\[✖\]/ {
            url = $0
            status = 0
            marker = index($0, marker_text)
            if (marker > 0) {
                url = substr($0, 1, marker - 1)
                # length(), not a literal: the arrow is three bytes in UTF-8 and the awk
                # on macOS is byte-oriented, so a hardcoded 10 lands mid-status and every
                # failure reads as status 0 - which classifies a real 404 as a blip.
                status = substr($0, marker + length(marker_text)) + 0
            }
            sub(/^ *\[✖\] */, "", url)
            gsub(/^[ \t]+|[ \t]+$/, "", url)
            if (url == "") next
            # The tool reports each failure twice: once in the per-file block with no
            # status, then again in its trailing summary with `→ Status:` attached. The
            # first sighting wins for ordering, but a later sighting carrying a status
            # replaces it - keeping the first would classify every real 404 as status 0
            # and send genuine dead links down the retry path.
            if (!(url in seen)) {
                seen[url] = 1
                order[++total] = url
                st[url] = status
            } else if (status != 0) {
                st[url] = status
            }
        }
        END {
            for (i = 1; i <= total; i++) {
                printf "%s\t%d\n", order[i], st[order[i]]
            }
        }
    '
}

# A 404 or 410 is the host saying the resource is gone: that is a dead link. Anything
# else - 429, any 5xx, or status 0 for a connection that never completed - is the host
# declining to answer right now, which is not evidence about the link.
#
# The distinction matters because GitHub rate-limits a concurrent scan hard enough to
# return 503, and treating that as a dead link made this gate fail on a clean tree with
# a different handful of URLs every run.
is_dead_status() {
    case "$1" in
        404 | 410) return 0 ;;
        *) return 1 ;;
    esac
}

# recheck_urls <markdown-file> <url>... : re-fetch specific URLs, leaving the final
# report in RECHECK_REPORT
#
# Only the failures are retried, so a blip costs a couple of requests rather than a
# second full scan. The URLs go into a generated Markdown file rather than being passed
# as arguments: markdown-link-check 3.15.0 accepts a URL as a positional argument,
# prints nothing at all, and exits 0, so a retry that way silently "recovers" every
# link and turns the gate into a no-op.
#
# `sleep` backoff grows because a rate-limited host needs time, not more immediate
# requests.
recheck_urls() {
    local output="$1"
    local url attempt
    local probe="$output.md"

    shift

    if [ "$#" -eq 0 ]; then
        return 0
    fi

    : > "$probe"
    for url in "$@"; do
        # Angle brackets keep a URL containing parentheses from ending the target early.
        printf '# Retry Probe\n\n[probe](<%s>)\n' "$url" >> "$probe"
    done

    for attempt in 1 2 3; do
        # The tool's exit status is discarded rather than piped into the test: it exits
        # 1 precisely because it found a dead link, and under `pipefail` that would make
        # the whole pipeline fail, so a guarded `!` would read a genuine failure as a
        # recovery and wave the link through.
        RECHECK_REPORT="$("$LINK_CHECK" "$probe" 2>&1)" || true

        if ! printf '%s' "$RECHECK_REPORT" | grep -q '✖'; then
            rm -f "$probe"

            return 0
        fi

        [ "$attempt" -lt 3 ] && sleep $((attempt * 2))
    done

    # The probe is left in place deliberately: the caller parses RECHECK_REPORT rather
    # than re-running the tool, so there is nothing left to read and nothing to clean.
    return 1
}

RECHECK_REPORT=""

failures="$(parse_failures "$raw_report")" || true

dead_links=""
inconclusive_links=""

if [ -n "$failures" ]; then
    RETRY_LIST="$(mktemp)"
    trap 'rm -f "$TARGET_LIST" "$RETRY_LIST" "$RETRY_LIST.md"' EXIT

    retry_urls=()
    while IFS=$'\t' read -r url status; do
        [ -n "$url" ] || continue
        case "$status" in
            '' | *[!0-9]*) status=0 ;;
        esac

        # Auth-gated hosts, vendored private repositories, and reserved placeholder
        # domains fail by design, so they are dropped before any verdict is formed.
        if is_exempt "$url"; then
            continue
        fi

        # Only a remote request can be a transient failure. A local path that does
        # not resolve is reported with a non-HTTP status (400 for the `file`
        # protocol), and retrying it three times with backoff delays the verdict and
        # then mislabels a broken link as merely unreachable.
        if is_dead_status "$status" || ! printf '%s' "$url" | grep -qE '^[a-zA-Z][a-zA-Z0-9+.-]*:'; then
            dead_links="$dead_links  [✖] $url → Status: $status"$'\n'
        else
            retry_urls+=("$url")
        fi
    done <<< "$failures"

    # Second opinion on everything that was not a 404. Most of these recover, because
    # the first answer came from a host under load rather than from a missing page.
    if [ "${#retry_urls[@]}" -gt 0 ]; then
        if recheck_urls "$RETRY_LIST" "${retry_urls[@]}"; then
            :
        else
            while IFS=$'\t' read -r url status; do
                [ -n "$url" ] || continue
                inconclusive_links="$inconclusive_links  [~] $url → Status: $status"$'\n'
            done <<< "$(parse_failures "$RECHECK_REPORT" || true)"
        fi
    fi
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

if [ -n "$(printf '%s' "$dead_links" | tr -d ' \n')" ]; then
    printf '%s' "$dead_links"
    echo "Link Lint Failed With $(printf '%s' "$dead_links" | grep -c '\[✖\]') Dead Link(s) Across $file_count File(s)"
    exit 1
fi

if [ -n "$(printf '%s' "$inconclusive_links" | tr -d ' \n')" ]; then
    # Reported as its own failure rather than folded into the dead-link count or, worse,
    # waved through: an unreachable host is not a broken link, but it is also not a
    # clean bill of health, and hiding it would make this gate lie.
    printf '%s' "$inconclusive_links"
    echo "Link Lint Failed: $(printf '%s' "$inconclusive_links" | grep -c '\[~\]') Link(s) Unreachable After 3 Attempts - Not Confirmed Dead"
    exit 1
fi

# Every dead link the tool found was exempt, so the non-zero status it returns for
# those is expected and not a failure. A non-zero status with a crash signature in
# the report is a real failure, though: reporting that as a pass would hide a broken
# gate, which is the failure mode this script exists to prevent.
# `grep -q` exits at the first match, which closes the pipe and makes `printf` report a
# broken pipe. `grep -c` reads every line instead, and its output is discarded here.
if printf '%s' "$raw_report" | grep -cE 'ENOENT|SyntaxError|Error:|Cannot find module' >/dev/null; then
    printf '%s\n' "$raw_report" >&2
    echo "Link Lint Failed: Checker Reported An Error" >&2
    exit 1
fi

echo "Link Lint Passed ($file_count File(s))"
