#!/usr/bin/env python3
"""Render GitHub release notes from CHANGELOG.md (laravel-api-skeleton conventions)."""

from __future__ import annotations

import re
import sys
from pathlib import Path

SECTION_MAP = {
    "Breaking Changes": "### ⚠️ Breaking Changes",
    "Added": "### ✅ Added",
    "Changed": "### 🔄 Changed",
    "Fixed": "### 🐛 Fixed",
    "Removed": "### ❌ Removed",
}

SECTION_ORDER = [
    "Breaking Changes",
    "Added",
    "Changed",
    "Fixed",
    "Removed",
]


def parse_footer_links(text: str) -> dict[str, str]:
    links: dict[str, str] = {}
    for line in text.splitlines():
        m = re.match(r"^\[(.+?)\]:\s+(\S+)\s*$", line)
        if m and re.match(r"^\d+\.\d+\.\d+$", m.group(1)):
            links[m.group(1)] = m.group(2)
    return links


def parse_versions(text: str) -> dict[str, dict]:
    versions: dict[str, dict] = {}
    current_ver: str | None = None
    current_section: str | None = None
    bullet_lines: list[str] = []
    sections: dict[str, list[str]] = {}

    def flush_bullet() -> None:
        nonlocal bullet_lines
        if current_ver and current_section and bullet_lines:
            joined = " ".join(s.strip() for s in bullet_lines if s.strip())
            sections.setdefault(current_section, []).append(joined)
        bullet_lines = []

    def start_version(ver: str, date: str) -> None:
        nonlocal current_ver, sections, current_section, bullet_lines
        flush_bullet()
        current_ver = ver
        sections = {k: [] for k in SECTION_ORDER}
        current_section = None
        bullet_lines = []
        versions[ver] = {"date": date, "sections": sections}

    for line in text.splitlines():
        vm = re.match(r"^## \[(\d+\.\d+\.\d+)\] - (\d{4}-\d{2}-\d{2})\s*$", line)
        if vm:
            start_version(vm.group(1), vm.group(2))
            continue
        if line.startswith("## [Unreleased]"):
            flush_bullet()
            current_ver = None
            continue
        if current_ver is None:
            continue
        if line.startswith("[") and "]: http" in line:
            flush_bullet()
            current_ver = None
            continue
        if line.startswith("### "):
            flush_bullet()
            name = line[4:].strip()
            current_section = name if name in SECTION_ORDER else None
            continue
        if current_section is None:
            continue
        if line.startswith("- "):
            flush_bullet()
            bullet_lines = [line]
        elif bullet_lines and line.strip():
            bullet_lines.append(line.strip())
        elif not line.strip():
            flush_bullet()

    flush_bullet()
    return versions


def render_release_body(version: str, data: dict, compare_url: str | None) -> str:
    parts: list[str] = []
    for section in SECTION_ORDER:
        bullets = [b for b in data["sections"].get(section, []) if b]
        if not bullets:
            continue
        parts.append(SECTION_MAP[section])
        parts.append("")
        parts.extend(bullets)
        parts.append("")
    while parts and parts[-1] == "":
        parts.pop()
    if version != "1.0.0" and compare_url:
        prev = compare_url.rsplit("/compare/", 1)[-1]
        parts.extend(["", "---", "", f"**Full Changelog**: [{prev}]({compare_url})"])
    return "\n".join(parts) + "\n"


def main() -> int:
    changelog = Path(__file__).resolve().parents[1] / "CHANGELOG.md"
    text = changelog.read_text(encoding="utf-8")
    links = parse_footer_links(text)
    versions = parse_versions(text)

    if len(sys.argv) > 1:
        target = sys.argv[1].lstrip("v")
        if target not in versions:
            print(f"Unknown version {target}", file=sys.stderr)
            return 1
        print(render_release_body(target, versions[target], links.get(target)), end="")
        return 0

    out_dir = Path("/tmp/laravel-api-skeleton-release-notes")
    out_dir.mkdir(exist_ok=True)
    count = 0
    for ver in sorted(versions.keys(), key=lambda v: [int(p) for p in v.split(".")]):
        body = render_release_body(ver, versions[ver], links.get(ver))
        (out_dir / f"v{ver}.md").write_text(body, encoding="utf-8")
        count += 1
    print(f"Wrote {count} files to {out_dir}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
