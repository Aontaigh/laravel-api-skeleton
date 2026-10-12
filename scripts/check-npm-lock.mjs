#!/usr/bin/env node
/**
 * Fail when the lock's root `engines` range disagrees with the manifest.
 *
 * `npm ci` validates the dependency tree but never compares the root package's own metadata, so
 * narrowing `engines.node` leaves a stale range in `package-lock.json` with nothing to notice it.
 * Refreshing the lock and diffing the whole file is not a check either: npm rewrites the root
 * `name` from the checkout directory, so a clone and CI produce different diffs for the same
 * commit. Comparing the field that actually drifts is honest in both places.
 */
import { readFileSync } from 'node:fs';

const manifest = JSON.parse(readFileSync(new URL('../package.json', import.meta.url)));
const lock = JSON.parse(readFileSync(new URL('../package-lock.json', import.meta.url)));

const declared = manifest.engines?.node ?? '';
const locked = lock.packages?.['']?.engines?.node ?? '';

if (declared !== locked) {
    console.error(
        `Lock File Engines Drifted: package.json declares ${declared}; package-lock.json records ${locked}`,
    );
    process.exit(1);
}

console.log(`Lock File Engines Match (${declared})`);
