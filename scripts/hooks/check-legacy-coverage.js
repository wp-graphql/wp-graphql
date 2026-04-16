#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

function parseArgs(argv = process.argv.slice(2)) {
	return argv.reduce((acc, arg) => {
		if (!arg.startsWith('--')) {
			return acc;
		}
		const [key, rawValue] = arg.slice(2).split('=');
		acc[key] = rawValue === undefined ? true : rawValue;
		return acc;
	}, {});
}

function getDefaultBaseRef() {
	if (process.env.GITHUB_EVENT_NAME === 'pull_request' && process.env.GITHUB_BASE_REF) {
		return `origin/${process.env.GITHUB_BASE_REF}`;
	}

	if (
		process.env.GITHUB_EVENT_NAME === 'push' &&
		process.env.GITHUB_EVENT_BEFORE &&
		!/^[0]+$/.test(process.env.GITHUB_EVENT_BEFORE)
	) {
		return process.env.GITHUB_EVENT_BEFORE;
	}

	return 'origin/main';
}

function readJson(filePath) {
	return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function tryReadJsonAtGitRef(ref, filePathFromRepoRoot) {
	try {
		const content = execSync(`git show "${ref}:${filePathFromRepoRoot}"`, {
			encoding: 'utf8',
			stdio: ['ignore', 'pipe', 'pipe'],
		});
		return JSON.parse(content);
	} catch (error) {
		return null;
	}
}

function toHookKey(kind, name) {
	return `${kind}:${name}`;
}

function getCodebackedWpgraphqlHookKeys(indexPayload) {
	const hooks = Array.isArray(indexPayload?.hooks) ? indexPayload.hooks : [];

	return new Set(
		hooks
			.filter((hook) => hook && hook.sourceType === 'wpgraphql')
			.filter((hook) => !hook.isDynamic && typeof hook.name === 'string' && hook.name.length > 0)
			.filter((hook) => hook.emitter !== 'legacy_registry' && hook.file !== '__legacy_registry__')
			.map((hook) => toHookKey(hook.kind, hook.name))
	);
}

function getLegacyHookEntriesByKey(legacyPayload, pluginSlug) {
	const entries = Array.isArray(legacyPayload?.[pluginSlug]) ? legacyPayload[pluginSlug] : [];
	const byKey = new Map();
	entries.forEach((entry) => {
		if (!entry || !entry.kind || !entry.name) {
			return;
		}
		byKey.set(toHookKey(entry.kind, entry.name), entry);
	});
	return byKey;
}

function main() {
	const args = parseArgs();
	const pluginSlug = args.plugin || 'wp-graphql';
	const baseRef = args['base-ref'] || getDefaultBaseRef();

	const repoRoot = process.cwd();
	const hooksIndexPath = path.join(
		repoRoot,
		'plugins',
		pluginSlug,
		'docs',
		'generated',
		'hooks-index.json'
	);
	const hooksIndexPathFromRepoRoot = path.relative(repoRoot, hooksIndexPath);
	const legacyHooksPath = path.join(repoRoot, 'scripts', 'hooks', 'legacy-hooks.json');

	if (!fs.existsSync(hooksIndexPath)) {
		console.error(`Error: missing generated hooks index at ${hooksIndexPathFromRepoRoot}`);
		console.error('Run `npm run hooks:generate -- --plugin=<plugin>` before this check.');
		process.exit(1);
	}

	if (!fs.existsSync(legacyHooksPath)) {
		console.error('Error: missing scripts/hooks/legacy-hooks.json');
		process.exit(1);
	}

	const currentIndex = readJson(hooksIndexPath);
	const baseIndex = tryReadJsonAtGitRef(baseRef, hooksIndexPathFromRepoRoot);
	const legacyPayload = readJson(legacyHooksPath);

	if (!baseIndex) {
		console.log(
			`No baseline hooks index found at ref "${baseRef}". Skipping legacy coverage check for ${pluginSlug}.`
		);
		process.exit(0);
	}

	const currentKeys = getCodebackedWpgraphqlHookKeys(currentIndex);
	const baseKeys = getCodebackedWpgraphqlHookKeys(baseIndex);
	const legacyByKey = getLegacyHookEntriesByKey(legacyPayload, pluginSlug);

	const removedKeys = [...baseKeys].filter((key) => !currentKeys.has(key));

	const missingLegacyEntries = removedKeys.filter((key) => !legacyByKey.has(key));
	const invalidLegacyEntries = removedKeys
		.map((key) => ({ key, entry: legacyByKey.get(key) }))
		.filter(({ entry }) => entry)
		.filter(({ entry }) => {
			const status = entry.status;
			const hasStatus = status === 'deprecated' || status === 'removed';
			const hasDeprecatedIn =
				typeof entry.deprecatedIn === 'string' && entry.deprecatedIn.trim().length > 0;
			return !hasStatus || !hasDeprecatedIn;
		});

	if (missingLegacyEntries.length === 0 && invalidLegacyEntries.length === 0) {
		console.log(
			`Legacy coverage check passed for ${pluginSlug}. Removed hooks detected: ${removedKeys.length}.`
		);
		process.exit(0);
	}

	console.error(
		`Legacy coverage check failed for ${pluginSlug}. Removed hooks must be preserved in scripts/hooks/legacy-hooks.json.`
	);

	if (missingLegacyEntries.length > 0) {
		console.error('\nMissing legacy entries:');
		missingLegacyEntries.forEach((key) => {
			const [kind, name] = key.split(':');
			console.error(`- ${name} (${kind})`);
		});
	}

	if (invalidLegacyEntries.length > 0) {
		console.error('\nInvalid legacy entries (need status deprecated|removed and deprecatedIn):');
		invalidLegacyEntries.forEach(({ key }) => {
			const [kind, name] = key.split(':');
			console.error(`- ${name} (${kind})`);
		});
	}

	console.error('\nExample entry template:');
	console.error(
		JSON.stringify(
			{
				name: 'graphql_example_hook',
				kind: 'action',
				status: 'removed',
				deprecatedIn: 'x.y.z',
				removedIn: 'a.b.c',
				replacement: 'graphql_replacement_hook',
				group: 'request-lifecycle',
				description: 'Legacy hook preserved for documentation history.',
			},
			null,
			2
		)
	);
	process.exit(1);
}

main();
