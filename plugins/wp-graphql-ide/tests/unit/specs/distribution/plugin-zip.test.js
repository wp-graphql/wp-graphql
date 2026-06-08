/**
 * Distribution-ZIP smoke test.
 *
 * Guards against the class of "ZIP missing critical files" bugs that
 * only surface at install-time on a clean environment (wp.org installer,
 * Playground, fresh wp-content/plugins install). The original failure
 * this test was written against:
 *
 *   - `package.json` `files` field listed `build`, `styles`,
 *     `plugins/*\/*.php`, etc. but omitted `includes`.
 *   - `wpgraphql-ide.php` opens with
 *     `require_once __DIR__ . '/includes/access-functions.php';`
 *   - The published archive therefore fataled on its first require, with
 *     no output captured (the fatal happened before any buffer flush),
 *     surfacing in Playground as the maddening:
 *       "PHP.run() failed with exit code 255"
 *       === Stdout === (empty)
 *       === Stderr === (empty)
 *
 * The structural shape of the ZIP — proper `<slug>/` top-level
 * directory; build output present; every literal `require_once __DIR__`
 * path actually shipped — is exactly what install-time consumers
 * (WordPress core's installer, Playground's `installPlugin` step,
 * wp.org's auto-installer) check before they touch the plugin. Asserting
 * the same invariants here catches the regression locally and inside
 * the Playground Preview workflow before a single end-user hits it.
 *
 * The require-once detection is parsed from the entry file itself, so
 * adding a new `require_once __DIR__ . '/includes/foo.php'` later is
 * automatically covered — no need to keep this test in sync by hand.
 */
const { execSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const AdmZip = require('adm-zip');

const PLUGIN_DIR = path.resolve(__dirname, '../../../..');
const ZIP_PATH = path.join(PLUGIN_DIR, 'wp-graphql-ide.zip');
const ENTRY_PATH = path.join(PLUGIN_DIR, 'wpgraphql-ide.php');
const SLUG = 'wp-graphql-ide';

describe('Distribution ZIP', () => {
	let entries;

	beforeAll(() => {
		// Rebuild from scratch so the assertions reflect the current
		// `files` list in package.json — not an artifact that may have
		// been built before a packaging change.
		execSync('node bin/build-plugin-zip.js', {
			cwd: PLUGIN_DIR,
			stdio: 'pipe',
		});
		entries = new AdmZip(ZIP_PATH)
			.getEntries()
			.map((entry) => entry.entryName);
	}, 30000);

	it('is built and exists at the workspace root', () => {
		expect(fs.existsSync(ZIP_PATH)).toBe(true);
	});

	it(`wraps every file under a single ${SLUG}/ top-level directory`, () => {
		// WP, wp.org, and Playground all expect this — without it, the
		// installer can't derive the plugin slug and `activatePlugin`
		// fails with "wasn't able to find the plugin /wp-content/plugins/<slug>".
		const violations = entries.filter(
			(name) => !name.startsWith(`${SLUG}/`)
		);
		expect(violations).toEqual([]);
	});

	it('contains the plugin entry file at the expected path', () => {
		expect(entries).toContain(`${SLUG}/wpgraphql-ide.php`);
	});

	it('ships every file the entry require_once()s', () => {
		// Walks `require_once __DIR__ . '/path/to/file.php';` patterns
		// out of the entry file. Each captured path is asserted to be
		// in the archive under the slug prefix.
		const source = fs.readFileSync(ENTRY_PATH, 'utf-8');
		const requirePattern =
			/require(?:_once)?\s+__DIR__\s*\.\s*['"]\/([^'"]+\.php)['"]/g;
		const required = Array.from(
			source.matchAll(requirePattern),
			(match) => match[1]
		);

		// Sanity check that the regex caught real lines — guards
		// against the test silently passing if the entry file is
		// renamed or the require syntax changes.
		expect(required.length).toBeGreaterThan(0);

		// Some paths are intentionally optional — wrapped in a
		// `file_exists()` guard. `vendor/autoload.php` is the obvious
		// one (the entry file falls back to a manual SPL autoloader
		// over `includes/` when it isn't present, which is the
		// expected state for Bedrock installs / cross-plugin CI runs
		// that didn't run composer install for the IDE workspace).
		// If we ever add a new conditional require, list it here.
		const optional = new Set(['vendor/autoload.php']);

		const missing = required.filter(
			(relative) =>
				!optional.has(relative) &&
				!entries.includes(`${SLUG}/${relative}`)
		);
		expect(missing).toEqual([]);
	});

	it('ships the webpack build output the IDE enqueues at runtime', () => {
		// Without these the IDE's wp_enqueue_script calls 404 in
		// production and the React app never mounts. Asserted
		// explicitly (rather than parsed from PHP) because the
		// enqueue layer is dynamic enough that scraping it would be
		// more brittle than just naming the canonical entrypoints.
		expect(entries).toContain(`${SLUG}/build/wpgraphql-ide.js`);
		expect(entries).toContain(`${SLUG}/build/wpgraphql-ide.asset.php`);
		expect(entries).toContain(
			`${SLUG}/build/wpgraphql-ide-render.js`
		);
		expect(entries).toContain(
			`${SLUG}/build/wpgraphql-ide-render.asset.php`
		);
	});
});
