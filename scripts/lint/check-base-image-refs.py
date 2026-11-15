#!/usr/bin/env python3
"""
Fail when a base image reference in a Dockerfile is unpinned or cannot be resolved.

Why this exists when the build already pulls those images: the build pulls them at
build time, which is the worst place to discover that a reference is gone. This
repository pins `docker/8.5/Dockerfile`'s Ubuntu base by tag and index digest for
reproducibility (Docker's build best practices,
https://docs.docker.com/build/building/best-practices/#pin-base-image-versions),
and a digest that the registry has pruned is exactly the failure a pin introduces.
The gate resolves every reference in review, so the first sign is a failed check
rather than a failed `sail build`. It also fails a registry reference that carries
no `@sha256:` digest, because the pin is the property that makes the resolution
check meaningful: a tag alone is free to move under a clone, and a regenerated
Sail runtime that drops the digest would otherwise pass this gate silently.

The reference forms read, in file order so an earlier stage can be recognised:

- `FROM <ref> [AS <stage>]`, including `FROM --platform=... <ref>`, where `<ref>`
  is either a registry reference or a stage declared earlier in the same file
- `COPY --from=<ref>` / `ADD --from=<ref>`, where `<ref>` is either a declared
  stage or a registry reference
- `FROM scratch`, the reserved empty base, which is never resolved

Two parsing rules come from the daemon rather than from the file's own shape: comment
lines are removed before anything is read, so an example inside a `#` line is not an
instruction; and a trailing backslash joins one instruction to the next physical line,
with the first line's number the one a report names.

Failing checks:

  1. A registry reference carries no `@sha256:` digest. The tag is mutable, so two
     clones of one commit could build different bases; the digest is the pin.
  2. A pinned reference does not resolve. A pruned digest is the failure a pin
     introduces, and review is the right place to catch it.

Resolution is done with `docker buildx imagetools inspect`, which reads the registry
index without pulling layers. A resolution failure is classified, because the causes
call for different actions:

- a rate-limit answer (`toomanyrequests`, `Data limit exceeded`, `429`), a `5xx`
  reason phrase, or a transport error before a status arrived - the registry answered
  badly, or did not answer at all. That is not evidence about the reference, so it is
  retried and then reported as inconclusive, not as a dead reference. Docker Hub's
  anonymous pull limit is the common case here.
- a `401`/`403` without a rate-limit marker - the registry refused it. An expired or
  absent login refuses a perfectly valid reference, so the report names the login
  remedy first and the re-pin second. The gate still fails either way, because a
  reference the build needs cannot be confirmed.
- anything else, typically `404` / `manifest unknown` - the reference does not
  resolve. That is the failure this gate exists to catch, and it is reported as such.

Runs on the Python standard library plus the Docker CLI with buildx - no third-party
packages - and is read-only apart from the registry metadata reads the probe performs.

Exit codes: 0 clean, 1 a reference does not resolve, 2 bad invocation or no Docker.
"""

from __future__ import annotations

import argparse
import re
import shutil
import subprocess
import sys
from pathlib import Path

# `FROM` with an optional `--platform=` flag, the reference, and an optional stage name.
# Deliberately not anchored at the end: Docker does not treat a mid-line `#` as a comment,
# and a `FROM` carrying anything after its image is still an instruction whose image has to
# be resolved rather than one to skip in silence.
FROM = re.compile(
    r"^\s*FROM\s+(?:--platform=\S+\s+)?(?P<ref>\S+)(?:\s+AS\s+(?P<stage>\S+))?",
    re.IGNORECASE,
)

# `COPY --from=` or `ADD --from=` taking a stage name or an image reference.
COPY_FROM = re.compile(r"--from=(?P<ref>[^\s]+)", re.IGNORECASE)

# The reserved empty base: nothing to resolve.
SCRATCH = "scratch"

# A retryable answer, which is not a missing reference: a registry rate limit, a `5xx`
# reason phrase, or a transport error before a status arrived. Each is retried and then
# reported as inconclusive. `unexpected status from HEAD request` is deliberately absent:
# it carries a `404` as readily as a `502`, and the `404` is the failure this gate exists
# to catch.
TRANSIENT_MARKERS = (
    "toomanyrequests",
    "data limit exceeded",
    "429",
    "timeout",
    "bad gateway",
    "service unavailable",
    "internal server error",
    "connection reset",
    "connection refused",
    "no such host",
)


def marker_hit(text: str, markers: tuple[str, ...]) -> bool:
    """Whether a marker appears in the text as a word rather than inside a digest.

    The tool echoes the reference it was asked about, digest included, so a plain substring
    test lets `sha256:` characters satisfy a marker: `401` occurs inside a hash often enough
    to matter, and a reference that is genuinely gone would be reported as a rate-limit or a
    refusal instead. A marker counts only when neither neighbouring character is alphanumeric,
    which is what separates the `401` of an error message from the same three characters
    inside a digest.
    """
    lowered = text.lower()

    for marker in markers:
        start = lowered.find(marker)

        while start != -1:
            before = lowered[start - 1] if start else ""
            end = start + len(marker)
            after = lowered[end] if end < len(lowered) else ""

            if not (before.isalnum() or after.isalnum()):
                return True

            start = lowered.find(marker, start + 1)

    return False


# A refusal without a rate-limit marker. It is reported with its own remedy: an expired or
# absent login refuses a valid reference, and a pruned digest answers the same way.
AUTH_MARKERS = ("401", "403", "unauthorized", "forbidden", "denied")

# An expired login, which refuses a reference that is perfectly valid: refresh the login
# before re-pinning anything.
AUTH_EXPIRED_MARKERS = ("token has expired",)

DOCKERFILE_GLOBS = ("Dockerfile", "docker/*/Dockerfile", "docker/**/Dockerfile")


def iter_dockerfiles(root: Path) -> list[Path]:
    """Every repository-owned Dockerfile, sorted for stable output."""
    found: list[Path] = []
    for pattern in DOCKERFILE_GLOBS:
        found.extend(root.glob(pattern))
    return sorted({path for path in found if path.is_file()})


def logical_lines(path: Path) -> list[tuple[int, str]]:
    """Instructions with comments removed and continuations joined.

    The daemon removes comment lines before it parses anything, so an example in a
    `#` line is not an instruction; and a trailing backslash continues one
    instruction across physical lines. Each entry keeps the number of the first line
    the instruction occupies, which is the line a report should name.
    """
    joined: list[tuple[int, str]] = []
    pending: str | None = None
    first = 0

    for number, raw in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
        if raw.strip().startswith("#"):
            continue

        text = raw.rstrip()
        if pending is None:
            pending, first = "", number
        pending += text[:-1] if text.endswith("\\") else text
        if not text.endswith("\\"):
            joined.append((first, pending))
            pending = None

    if pending is not None:
        joined.append((first, pending))

    return joined


def references(path: Path) -> list[tuple[int, str]]:
    """Every registry reference in one Dockerfile, with the line it appears on.

    Stage names are collected as the instructions are read, because a later `FROM`
    or `COPY --from=` may name a stage declared above it; those are build stages,
    not images, and probing one reports a false failure.
    """
    found: list[tuple[int, str]] = []
    stages: set[str] = set()

    for number, line in logical_lines(path):
        match = FROM.match(line)
        if match:
            base = match.group("ref")
            if base.lower() != SCRATCH and base not in stages:
                found.append((number, base))
            if match.group("stage"):
                stages.add(match.group("stage"))
            continue

        for copy in COPY_FROM.finditer(line):
            candidate = copy.group("ref")
            # `COPY --from=0` names a stage by index, not a registry reference.
            if candidate not in stages and not candidate.isdigit():
                found.append((number, candidate))

    return found


def is_digest_pinned(reference: str) -> bool:
    """Whether a registry reference carries a content digest rather than only a tag.

    A tag is mutable: the publisher can repoint it at a new image without a commit
    here, so two clones of one commit can build different bases. The digest is what
    makes the reference immutable, and the tag beside it keeps the line readable.
    """
    return "@sha256:" in reference


def resolves(reference: str, attempts: int) -> tuple[bool, str]:
    """Whether a reference resolves, with the tool's own error text for the report."""
    last = ""
    for _ in range(attempts):
        try:
            result = subprocess.run(
                ["docker", "buildx", "imagetools", "inspect", reference],
                capture_output=True,
                text=True,
                timeout=120,
            )
        except subprocess.TimeoutExpired:
            last = "probe timed out"
            continue

        if result.returncode == 0:
            return True, ""

        output = (result.stderr or result.stdout).strip()
        last = output.splitlines()[-1] if output else "no diagnostic from docker"
        if not any(marker in last.lower() for marker in TRANSIENT_MARKERS):
            return False, last

    return False, last


def check(root: Path, attempts: int) -> tuple[list[str], list[str], int]:
    """One message per unresolvable reference, one per inconclusive probe, and a count."""
    dead: list[str] = []
    inconclusive: list[str] = []
    probed = 0
    seen: set[str] = set()

    for path in iter_dockerfiles(root):
        relative = path.relative_to(root).as_posix()
        for number, reference in references(path):
            if reference in seen:
                continue
            seen.add(reference)
            probed += 1
            if not is_digest_pinned(reference):
                dead.append(
                    f"FAIL: {relative}:{number}: `{reference}` is not pinned by digest - "
                    f"add `@sha256:...` so a republished tag cannot change the base under a clone"
                )
                continue
            ok, detail = resolves(reference, attempts)
            if ok:
                print(f"OK    {relative}:{number}: {reference}")
                continue
            finding = f"{relative}:{number}: `{reference}` does not resolve - {detail}"
            entry = f"FAIL: {finding}"

            if marker_hit(detail, TRANSIENT_MARKERS):
                # Transient means "not verified", and the run still exits 0 on this path, so the
                # line must not carry the FAIL: label the real failures use.
                inconclusive.append(f"NOTE: {finding}")
            elif marker_hit(detail, AUTH_EXPIRED_MARKERS):
                # An expired login refuses a valid reference, so refreshing the login comes
                # before the re-pin that fits a pruned digest.
                dead.append(
                    f"{entry} (the registry refused it with an expired login - refresh the "
                    f"login (`docker login <registry>`) and retry; only re-pin if the reference "
                    f"still does not resolve)"
                )
            elif any(marker in detail.lower() for marker in AUTH_MARKERS):
                # A refusal is a different finding from an absent reference, and a different
                # remedy follows. An expired or absent login refuses a valid digest the same
                # way, and the tool does not always surface which it was, so that remedy leads.
                # The gate still fails: a reference the build needs cannot be confirmed.
                dead.append(
                    f"{entry} (the registry refused it: refresh the login with `docker login "
                    f"<registry>` if the image is private or the token expired; if it still "
                    f"does not resolve, the pinned digest has probably been pruned - re-pin it)"
                )
            else:
                dead.append(entry)

    return dead, inconclusive, probed


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[1])
    parser.add_argument(
        "--root",
        default=str(Path(__file__).resolve().parents[2]),
        help="repository root (default: the repository this script lives in)",
    )
    parser.add_argument("--attempts", type=int, default=3, help="probe attempts before reporting (default: 3)")
    arguments = parser.parse_args()

    if shutil.which("docker") is None:
        print("Error: docker is not on PATH, so nothing was verified", file=sys.stderr)
        return 2

    buildx = subprocess.run(
        ["docker", "buildx", "version"],
        capture_output=True,
        text=True,
    )
    if buildx.returncode != 0:
        print("Error: docker buildx is not available, so nothing was verified", file=sys.stderr)
        return 2

    daemon = subprocess.run(
        ["docker", "info"],
        capture_output=True,
        text=True,
    )
    if daemon.returncode != 0:
        print("Error: the Docker daemon is not available, so nothing was verified", file=sys.stderr)
        return 2

    root = Path(arguments.root).resolve()
    dead, inconclusive, probed = check(root, max(1, arguments.attempts))

    # A probe count of zero means the root holds no Dockerfile to read, which is a wrong root
    # rather than a clean repository: exit 0 here would report "resolved" for a gate that
    # verified nothing at all.
    if probed == 0:
        print(f"Error: no Dockerfile reference was found under {root}, so nothing was verified", file=sys.stderr)
        return 2

    for message in inconclusive:
        print(f"  {message}", file=sys.stderr)

    if dead:
        print(f"Base Image Reference Check Failed ({len(dead)} Reference(s)):", file=sys.stderr)
        for message in dead:
            print(f"  {message}", file=sys.stderr)
        return 1

    if inconclusive:
        print(
            f"Base Image Reference Check Inconclusive ({len(inconclusive)} of {probed} Reference(s)): "
            "the registry rate-limited the probe or failed to answer, so those references were not verified",
            file=sys.stderr,
        )
        return 0

    print(f"Base Image References Resolved ({probed} Reference(s))")
    return 0


if __name__ == "__main__":
    sys.exit(main())
