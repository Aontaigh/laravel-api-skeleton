#!/usr/bin/env python3
"""
Fail when a workflow selects a **moving** GitHub-hosted runner alias.

Why this exists when `actionlint` already runs:

- `actionlint` validates `runs-on` against its own list of labels. `ubuntu-latest` is on
  that list, so it can never object to it. Proven: the pinned 1.7.12 image exits 0 on a
  workflow that is 100% `ubuntu-latest`.
- `actionlint` also cannot statically evaluate `${{ ... }}`, so the expression form of a
  runner label is invisible to it - in both directions. That is why a repository can move
  to the defaulted-variable pattern and still ship `|| 'ubuntu-latest'` as the fallback.

`ubuntu-latest` is not a version. GitHub moves it between LTS releases on a multi-week
rollout (actions/runner-images#14748), which annotates every job on the run page and
changes the image under the pipeline with no commit to review.

The rule this encodes is deliberately **not** "use ubuntu-26.04". That would need editing
the day 28.04 ships, which is the same staleness this gate exists to prevent. The invariant
is: name an OS major, or resolve one from a variable, and never an alias that moves.

A single deliberate exception is allowed, because the house rule keeps `ubuntu-latest`
reasonable for a throwaway gate. It must be justified inline:

    runs-on: ubuntu-latest  # runner-policy: allow - throwaway docs preview

The reason is required and must be non-empty; a bare marker fails.

The `runs-on` forms this gate reads:

- a same-line scalar, quoted or bare: `runs-on: ubuntu-latest`
- a same-line flow sequence: `runs-on: [ubuntu-latest, self-hosted]`
- a same-line flow mapping: `runs-on: {group: linux-runners, labels: ubuntu-latest}`
- a block sequence under an empty key, its items at the key's own indentation or deeper:

      runs-on:
        - ubuntu-latest

- a block mapping under an empty key, its pairs deeper than the key:

      runs-on:
        group: linux-runners
        labels: ubuntu-latest

A comment is split from the value at the `#` that whitespace precedes, so no comment hides
the alias it annotates: a tab, a space, or two spaces separate the two the same way, and a
value that is nothing but a comment leaves the block below it in scope.

One form is deliberately out of scope: a label that arrives **through a matrix**, as in
`runs-on: ${{ matrix.os }}`. The alias then lives in `strategy.matrix`, which this gate never
parses, so a moving alias chosen there passes. That limit is stated here on purpose: the gate
should not look broader than it is. Widen it by resolving the matrix, not by trusting the
expression.

Runs on the Python standard library only - no third-party packages - and is read-only.

Exit codes: 0 clean, 1 policy violation, 2 bad invocation.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

# A moving alias is any label ending in `-latest` (optionally with a suffix), which covers
# `ubuntu-latest`, `ubuntu-latest-8-cores`, `windows-latest`, and `macos-latest`. An
# OS-major label such as `ubuntu-26.04` or `ubuntu-24.04-arm` does not match.
MOVING_ALIAS = re.compile(r"^[A-Za-z0-9._-]*-latest(?:-[A-Za-z0-9._-]+)?$")

# `runs-on` with a same-line value: a scalar, a flow sequence, or a flow mapping. An empty
# value means the labels or the `group`/`labels` pairs follow as a block, which `check`
# reads from the lines below the key with `block_entries`.
RUNS_ON = re.compile(r"^\s*-?\s*runs-on\s*:\s*(?P<value>.*?)\s*$")

# The whitespace run that separates the value of a `runs-on` line from the comment after
# it: a YAML comment starts at a `#` which opens the text or follows whitespace, so the
# value is everything before that whitespace and the comment is everything from the `#` on,
# marker included. Splitting on the literal `" #"` instead hands the alias regex a value
# still glued to its comment, the `$` anchor fails, and the gate reports the line clean - a
# comment silently exempting an alias, which is the failure mode this script exists to
# prevent. A tab is valid separation before a comment: YAML 1.2.2 says "tab characters must
# not be used in indentation, since different systems treat tabs differently", which
# governs indentation, not the gap before a note.
COMMENT = re.compile(r"\s+(?=#)")

# A block sequence entry: the `-` indicator and the whitespace YAML requires after it, so a
# plain scalar that merely opens with a dash (`-ubuntu-latest`) is not read as a label.
SEQUENCE_ITEM = re.compile(r"^-[ \t]+")

# Any single- or double-quoted string inside a `runs-on` expression, so a fallback such as
# `${{ vars.RUNNER || 'ubuntu-latest' }}` is caught even though the expression itself is
# opaque to static analysis.
STRING_LITERAL = re.compile(r"'([^']*)'|\"([^\"]*)\"")

ALLOW_MARKER = "runner-policy: allow"

WORKFLOW_GLOBS = ("*.yml", "*.yaml")


def iter_workflows(root: Path) -> list[Path]:
    """Every workflow file under `.github/workflows`, sorted for stable output."""
    directory = root / ".github" / "workflows"
    if not directory.is_dir():
        return []
    found: list[Path] = []
    for pattern in WORKFLOW_GLOBS:
        found.extend(directory.rglob(pattern))
    return sorted(set(found))


def split_comment(text: str) -> tuple[str, str]:
    """The value of a `runs-on` line or block entry and the comment that follows it.

    The comment starts at the first `#` that opens the text or follows whitespace, and it
    keeps its `#`, which is what `verdict` reads for an allow marker. A text that is a
    comment and nothing else has an empty value, so a note on the key's own line leaves
    the block below it in scope instead of standing in for the value.
    """
    stripped = text.strip()
    if stripped.startswith("#"):
        return "", stripped
    parts = COMMENT.split(stripped, maxsplit=1)
    return parts[0].strip(), parts[1] if len(parts) > 1 else ""


def moving_values(value: str) -> list[str]:
    """Every moving alias in a `runs-on` value or block entry.

    Handles a bare scalar, a quoted scalar, the items of a flow sequence, and the values of
    a flow mapping such as `{group: linux-runners, labels: ubuntu-latest}`; a quoted
    literal inside an opaque `${{ ... }}` expression is the last resort.
    """
    hits: list[str] = []
    # `strip` peels the flow punctuation, then the comma split yields the items; a plain
    # scalar is the single item of its own list, so both shapes take the same path. An item
    # that is a mapping pair contributes the text after its first colon too, which is where
    # the value of `group:` or `labels:` sits.
    for item in value.strip().strip("{}[]").split(","):
        segments = [item]
        if ":" in item:
            segments.append(item.partition(":")[2])
        for segment in segments:
            bare = segment.strip().strip("{}[]\"'").strip()
            if bare and MOVING_ALIAS.match(bare):
                hits.append(bare)
    if hits:
        return hits
    for match in STRING_LITERAL.finditer(value):
        literal = (match.group(1) or match.group(2) or "").strip()
        if literal and MOVING_ALIAS.match(literal):
            hits.append(literal)
    return hits


def verdict(relative: str, number: int, hits: list[str], comment: str) -> list[str]:
    """The violation messages for one set of alias hits, honoring an allow marker."""
    if not hits:
        return []

    if ALLOW_MARKER in comment:
        reason = comment.split(ALLOW_MARKER, 1)[1].lstrip(" -:")
        if reason.strip():
            return []
        return [f"{relative}:{number}: `{hits[0]}` carries an allow marker with no reason"]

    return [
        (
            f"{relative}:{number}: `{hits[0]}` is a moving alias, not a version. "
            f"Use an OS major such as `ubuntu-26.04`, or resolve one from a variable "
            f"(`runs-on: ${{{{ vars.GH_RUNNER_NAME || 'ubuntu-26.04' }}}}`). "
            f"Justified exceptions need `# {ALLOW_MARKER} - <reason>` on the same line."
        )
    ]


def block_entries(lines: list[str], number: int, key_indent: int) -> list[tuple[int, str]]:
    """The `(line number, value)` of every entry in the block under an empty `runs-on:`.

    The block is a sequence, each entry `-` followed by whitespace and then a label, or a
    mapping of `group:`/`labels:` pairs. A sequence entry may sit at the key's own
    indentation or deeper, which YAML allows; a mapping entry must be deeper, since an entry
    level with the key would be a sibling key of the job (`steps:`, `env:`). A line that is
    neither ends the block, so the scan stops at the next key instead of reading into it.
    """
    entries: list[tuple[int, str]] = []
    for offset, line in enumerate(lines[number:], start=number + 1):
        entry = line.strip()
        if not entry or entry.startswith("#"):
            continue
        indent = len(line) - len(line.lstrip())
        if indent < key_indent:
            break
        if SEQUENCE_ITEM.match(entry):
            value = entry[1:]
        elif indent > key_indent and ":" in entry:
            # A `group:`/`labels:` pair, whose value is everything after the first colon;
            # a `labels:` with no value there takes its labels from a nested sequence.
            value = entry.partition(":")[2]
        else:
            break
        entries.append((offset, value.strip()))
    return entries


def check(root: Path) -> list[str]:
    """Return one message per violation; an empty list means the policy holds."""
    violations: list[str] = []
    for path in iter_workflows(root):
        try:
            lines = path.read_text(encoding="utf-8").splitlines()
        except (OSError, UnicodeDecodeError) as error:
            violations.append(f"{path}: could not be read ({error})")
            continue

        relative = path.relative_to(root).as_posix()
        for number, line in enumerate(lines, start=1):
            match = RUNS_ON.match(line)
            if not match:
                continue
            # Split the value from any trailing comment *before* testing it. `runs-on` is
            # captured to end-of-line, so `runs-on: ubuntu-latest  # runner-policy: allow
            # - reason` hands the alias regex the comment as well, the `$` anchor fails to
            # match, and the gate skips the line silently. That is the worst possible
            # failure for this script: it exempts exactly the lines a human wrote a note
            # on, which is where the interesting cases are.
            value, comment = split_comment(match.group("value"))
            if value:
                violations.extend(verdict(relative, number, moving_values(value), comment))
                continue

            # An empty value opens a block below the key: a sequence of labels, or a
            # mapping of `group:`/`labels:` pairs. `block_entries` reads it and says where
            # it ends.
            key_indent = len(line) - len(line.lstrip())
            for offset, entry in block_entries(lines, number, key_indent):
                entry_value, entry_comment = split_comment(entry)
                violations.extend(
                    verdict(relative, offset, moving_values(entry_value), entry_comment)
                )
    return violations


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument(
        "--repo-root",
        default=str(Path(__file__).resolve().parent.parent.parent),
        help="Repository root to scan (default: the repository root above this script's directory)",
    )
    args = parser.parse_args(argv)

    root = Path(args.repo_root).resolve()
    if not root.is_dir():
        print(f"Error: not a directory: {root}", file=sys.stderr)
        return 2

    workflows = iter_workflows(root)
    if not workflows:
        print(f"Runner Label Lint Skipped (No Workflows Under {root / '.github' / 'workflows'})")
        return 0

    violations = check(root)
    if violations:
        print(f"Runner Label Lint Failed ({len(violations)} Violation(s)):", file=sys.stderr)
        for violation in violations:
            print(f"  FAIL: {violation}", file=sys.stderr)
        print(
            "\nA moving alias changes the image under the pipeline with no commit to "
            "review (actions/runner-images#14748). See scripts/lint/lint-runner-labels.py.",
            file=sys.stderr,
        )
        return 1

    print(f"Runner Label Lint Passed ({len(workflows)} Workflow File(s))")
    return 0


if __name__ == "__main__":
    sys.exit(main())
