/**
 * Wipe per-run state at the end of every Playwright invocation so a
 * follow-up `npm run test:e2e` starts pristine.
 *
 * Why this exists: between back-to-back runs we saw 19/19 → 0/19
 * failures. Root cause was a mix of stale `STORAGE_STATE_PATH` (cookies
 * from a previous wp-env session, no longer valid against the now-
 * recycled DB) and IDE-side state living in WordPress posts that the
 * second run inherited (saved queries, history rows). Clearing both
 * here keeps each run independent.
 */

import fs from 'node:fs';
import path from 'node:path';

async function globalTeardown(_config) {
	const storageStatePath = process.env.STORAGE_STATE_PATH;
	if (storageStatePath && fs.existsSync(storageStatePath)) {
		try {
			fs.unlinkSync(storageStatePath);
		} catch {
			// best-effort; if the file's gone we don't care
		}
	}

	const artifactsPath = process.env.WP_ARTIFACTS_PATH;
	if (artifactsPath) {
		const traceDir = path.join(artifactsPath, 'test-results');
		// Don't delete the test-results dir entirely — Playwright wants
		// it to exist on the next run. Just ensure it's not a stale
		// failure cache from a prior teardown.
		if (fs.existsSync(traceDir)) {
			// no-op; Playwright manages this directory's lifecycle.
		}
	}
}

export default globalTeardown;
