#!/usr/bin/env node

/**
 * Reconcile the shared .release-please-manifest.json on a release PR branch
 * against main.
 *
 * The manifest holds every plugin's released version in one file, but
 * release-please only refreshes a given release PR when THAT plugin's own
 * changelog changes. So when a sibling plugin releases and main's manifest
 * advances, an open release PR keeps the sibling's old version. git can no
 * longer auto-merge the stale line against main and the PR goes
 * CONFLICTING/DIRTY — and, if force-merged, would regress the sibling's
 * version back down on main.
 *
 * The correct resolution is deterministic: take every line from main except
 * this PR's own component, which keeps its release bump. Doing that keeps the
 * PR mergeable no matter how many sibling releases land first.
 *
 * Usage:
 *   node scripts/reconcile-release-manifest.js --component=wp-graphql-ide
 *
 * Options:
 *   --component        Component being released (e.g. wp-graphql-ide). Required.
 *   --manifest         Path to the branch manifest (default
 *                      .release-please-manifest.json).
 *   --main-manifest    Path to a file holding main's manifest. When omitted,
 *                      it is read from git as origin/main:<manifest>. The flag
 *                      exists so tests can run without a git remote.
 */

const fs = require('fs');
const { execFileSync } = require('child_process');

const DEFAULT_MANIFEST = '.release-please-manifest.json';

function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		const [key, value] = arg.replace(/^--/, '').split('=');
		args[key] = value;
	});
	return args;
}

/**
 * Compute the reconciled manifest: main's versions for every plugin, with the
 * released component's own bump preserved from the branch.
 *
 * When the component is absent from either manifest there is nothing safe to
 * reconcile, so the branch manifest is returned by reference. Otherwise a new
 * object is returned; it may be deep-equal to the branch manifest when nothing
 * has drifted, so callers decide "no change" by value (see `main()`), not by
 * reference identity.
 *
 * @param {Object} mainManifest   Parsed manifest from main.
 * @param {Object} branchManifest Parsed manifest from the release PR branch.
 * @param {string} component      Component being released (e.g. wp-graphql-ide).
 * @return {Object} The reconciled manifest.
 */
function reconcileManifest(mainManifest, branchManifest, component) {
	const ownKey = `plugins/${component}`;

	// If the branch doesn't track this component, or main has no opinion on
	// it, there's nothing safe to reconcile — leave the branch as-is.
	if (!(ownKey in branchManifest) || !(ownKey in mainManifest)) {
		return branchManifest;
	}

	// Start from main so every sibling line matches the released truth and
	// main's key order is preserved, then keep this PR's own bump.
	return { ...mainManifest, [ownKey]: branchManifest[ownKey] };
}

function readMainManifest(args, manifestPath) {
	if (args['main-manifest']) {
		return fs.readFileSync(args['main-manifest'], 'utf8');
	}
	// execFileSync (not execSync) so manifestPath is passed as an argv entry
	// rather than interpolated into a shell string — no shell, no quoting or
	// injection surface.
	return execFileSync('git', ['show', `origin/main:${manifestPath}`], {
		encoding: 'utf8',
	});
}

function main() {
	const args = parseArgs();

	if (!args.component) {
		console.error('--component is required');
		process.exit(1);
	}

	const manifestPath = args.manifest || DEFAULT_MANIFEST;

	if (!fs.existsSync(manifestPath)) {
		console.log(`No ${manifestPath} found; skipping reconcile.`);
		process.exit(0);
	}

	const branchManifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

	let mainManifest;
	try {
		mainManifest = JSON.parse(readMainManifest(args, manifestPath));
	} catch (e) {
		console.log(
			`Could not read manifest from main (${e.message}); skipping reconcile.`
		);
		process.exit(0);
	}

	const reconciled = reconcileManifest(
		mainManifest,
		branchManifest,
		args.component
	);

	if (JSON.stringify(reconciled) === JSON.stringify(branchManifest)) {
		console.log('Manifest already reconciled with main; nothing to do.');
		process.exit(0);
	}

	fs.writeFileSync(
		manifestPath,
		`${JSON.stringify(reconciled, null, 2)}\n`,
		'utf8'
	);
	console.log(
		`Reconciled ${manifestPath} sibling versions with main for ${args.component}.`
	);
}

if (require.main === module) {
	main();
}

module.exports = { reconcileManifest };
