#!/usr/bin/env node

/**
 * Replace x-release-please-version placeholders in a component's legacy-hook
 * entries with the version being released.
 *
 * scripts/hooks/legacy-hooks.json records deprecated hooks per component, and
 * their `@since`-style version fields carry the x-release-please-version
 * placeholder until the release PR is cut. release-please rewrites the
 * placeholder in source files but not in this JSON, so the release workflow
 * fills it in here.
 *
 * Usage:
 *   node scripts/hooks/replace-legacy-hook-versions.js \
 *     --component=wp-graphql --version=2.7.0
 *
 * Options:
 *   --component  Component being released (e.g. wp-graphql). Required.
 *   --version    Version to substitute for the placeholder. Required.
 *   --file       Path to the legacy-hooks JSON (default
 *                scripts/hooks/legacy-hooks.json).
 */

const fs = require('fs');
const path = require('path');

const PLACEHOLDER = 'x-release-please-version';
const DEFAULT_FILE = path.join(__dirname, 'legacy-hooks.json');

function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		const [key, value] = arg.replace(/^--/, '').split('=');
		args[key] = value;
	});
	return args;
}

/**
 * Recursively replace every placeholder occurrence in a JSON-ish value
 * (string, array, or plain object) with `version`. Non-strings pass through.
 *
 * @param {*}      value   The value to walk.
 * @param {string} version Replacement version.
 * @return {*} A new value with placeholders replaced.
 */
function replacePlaceholders(value, version) {
	if (typeof value === 'string') {
		return value.split(PLACEHOLDER).join(version);
	}

	if (Array.isArray(value)) {
		return value.map((item) => replacePlaceholders(item, version));
	}

	if (value && typeof value === 'object') {
		return Object.fromEntries(
			Object.entries(value).map(([key, nested]) => [
				key,
				replacePlaceholders(nested, version),
			])
		);
	}

	return value;
}

/**
 * Replace placeholders in one component's entries within a legacy-hooks
 * payload.
 *
 * Returns `{ payload, changed, reason }`. `changed` is false (and `payload`
 * is the input unchanged) when there was nothing to do; `reason` says why, so
 * callers can log the distinct cases:
 *   - 'no-entries'     — the component has no entries array (missing/malformed)
 *   - 'no-placeholders'— entries exist but none held a placeholder
 *   - 'updated'        — placeholders were replaced (changed === true)
 *
 * @param {Object} payload   Parsed legacy-hooks.json.
 * @param {string} component Component key (e.g. wp-graphql).
 * @param {string} version   Replacement version.
 * @return {{ payload: Object, changed: boolean, reason: string }}
 */
function replaceComponentVersions(payload, component, version) {
	const entries =
		payload && typeof payload === 'object' ? payload[component] : null;

	if (!Array.isArray(entries)) {
		return { payload, changed: false, reason: 'no-entries' };
	}

	const updated = replacePlaceholders(entries, version);
	if (JSON.stringify(updated) === JSON.stringify(entries)) {
		return { payload, changed: false, reason: 'no-placeholders' };
	}

	return {
		payload: { ...payload, [component]: updated },
		changed: true,
		reason: 'updated',
	};
}

function main() {
	const args = parseArgs();

	// A no-op skip, not a hard failure, when a value is missing: this step
	// only fills placeholders in a JSON that is usually empty, so it must
	// never block the release PR update. (The workflow also guards with
	// `if: version != ''`; this keeps the script resilient on its own, as the
	// original inline step was.)
	if (!args.component || !args.version) {
		console.log('Missing --component or --version; skipping.');
		process.exit(0);
	}

	const filePath = args.file || DEFAULT_FILE;

	if (!fs.existsSync(filePath)) {
		console.log(`No ${filePath} found; skipping.`);
		process.exit(0);
	}

	const payload = JSON.parse(fs.readFileSync(filePath, 'utf8'));
	const {
		payload: updated,
		changed,
		reason,
	} = replaceComponentVersions(payload, args.component, args.version);

	if (!changed) {
		console.log(
			reason === 'no-entries'
				? `No legacy hook entries for ${args.component}; skipping.`
				: `No legacy hook placeholders to replace for ${args.component}.`
		);
		process.exit(0);
	}

	fs.writeFileSync(filePath, `${JSON.stringify(updated, null, 2)}\n`, 'utf8');
	console.log(`Updated ${filePath} placeholders for ${args.component}.`);
}

if (require.main === module) {
	main();
}

module.exports = { replacePlaceholders, replaceComponentVersions };
