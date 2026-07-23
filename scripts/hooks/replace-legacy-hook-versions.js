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
 * Returns `{ payload, changed }`. `changed` is false (and `payload` is the
 * input unchanged) when the component has no entries or nothing matched, so
 * callers can skip writing.
 *
 * @param {Object} payload   Parsed legacy-hooks.json.
 * @param {string} component Component key (e.g. wp-graphql).
 * @param {string} version   Replacement version.
 * @return {{ payload: Object, changed: boolean }}
 */
function replaceComponentVersions(payload, component, version) {
	const entries =
		payload && typeof payload === 'object' ? payload[component] : null;

	if (!Array.isArray(entries)) {
		return { payload, changed: false };
	}

	const updated = replacePlaceholders(entries, version);
	if (JSON.stringify(updated) === JSON.stringify(entries)) {
		return { payload, changed: false };
	}

	return { payload: { ...payload, [component]: updated }, changed: true };
}

function main() {
	const args = parseArgs();

	if (!args.component || !args.version) {
		console.error('--component and --version are required');
		process.exit(1);
	}

	const filePath = args.file || DEFAULT_FILE;

	if (!fs.existsSync(filePath)) {
		console.log(`No ${filePath} found; skipping.`);
		process.exit(0);
	}

	const payload = JSON.parse(fs.readFileSync(filePath, 'utf8'));
	const { payload: updated, changed } = replaceComponentVersions(
		payload,
		args.component,
		args.version
	);

	if (!changed) {
		console.log(
			`No legacy hook placeholders to replace for ${args.component}.`
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
