#!/usr/bin/env node
/**
 * Fail when the lock's root `engines` range disagrees with the manifest.
 *
 * `npm ci` validates the dependency tree but never compares the root package's own metadata, so
 * narrowing `engines.node` leaves a stale range in `package-lock.json` with nothing to notice it.
 * Refreshing the lock and diffing the whole file is not a check either: npm rewrites the root
 * `name` from the checkout directory, so a clone and CI produce different diffs for the same
 * commit. Comparing the fields that actually drift is honest in both places.
 *
 * Both engines are compared because both are load-bearing: `engines.node` is the runtime range
 * every gate installs, and `engines.npm` is the floor that makes `min-release-age` in `.npmrc`
 * mean anything. npm added that setting in 11.10.0 (npm/cli#8965); an older npm ignores it
 * silently, so `engine-strict=true` plus this range turns the silent no-op into `EBADENGINE`.
 */
import { readFileSync } from 'node:fs';

const manifest = JSON.parse(readFileSync(new URL('../package.json', import.meta.url)));
const lock = JSON.parse(readFileSync(new URL('../package-lock.json', import.meta.url)));

const fields = ['node', 'npm'];
let drifted = false;

for (const field of fields) {
    const declared = manifest.engines?.[field] ?? '';
    const locked = lock.packages?.['']?.engines?.[field] ?? '';

    if (declared !== locked) {
        console.error(
            `Lock File Engines Drifted: package.json declares engines.${field} ${declared}; package-lock.json records ${locked}`,
        );
        drifted = true;
    }
}

if (drifted) {
    process.exit(1);
}

console.log(
    `Lock File Engines Match (node ${manifest.engines.node}; npm ${manifest.engines.npm})`,
);
