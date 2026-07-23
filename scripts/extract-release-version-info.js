#!/usr/bin/env node

/**
 * Extract the component, version, and plugin directory for a release PR from
 * its title and branch name.
 *
 * Mirrors what release-please PRs look like:
 *   - title:  "chore(main): release wp-graphql 2.7.0"
 *   - branch: "release-please--branches--main--components--wp-graphql"
 *
 * The component comes from the title ("release <component>"), falling back to
 * the branch's "--components--<component>" suffix. The version comes from the
 * title (first X.Y.Z), falling back to the newest heading in the component's
 * CHANGELOG.md.
 *
 * Prints `key=value` lines (version, component, plugin_dir) to stdout so the
 * workflow can append them to $GITHUB_OUTPUT:
 *
 *   node scripts/extract-release-version-info.js \
 *     --pr-title="$PR_TITLE" --branch="$BRANCH_NAME" >> "$GITHUB_OUTPUT"
 *
 * Options:
 *   --pr-title     Pull request title. Required (may be empty).
 *   --branch       Head branch name. Required (may be empty).
 *   --plugins-dir  Directory holding the plugin folders (default "plugins").
 *                  Exists so tests can point at a fixture tree.
 */

const fs = require('fs');
const path = require('path');

const SEMVER = /[0-9]+\.[0-9]+\.[0-9]+/;

function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		const eq = arg.indexOf('=');
		if (arg.startsWith('--') && eq !== -1) {
			args[arg.slice(2, eq)] = arg.slice(eq + 1);
		}
	});
	return args;
}

/**
 * Component being released: the word after "release " in the PR title, else
 * the segment after "--components--" in the branch name.
 *
 * @param {string} prTitle Pull request title.
 * @param {string} branch  Head branch name.
 * @return {string} Component (empty string if neither source yields one).
 */
function parseComponent(prTitle, branch) {
	const fromTitle = (prTitle || '').match(/release\s+([a-z0-9-]+)/);
	if (fromTitle) {
		return fromTitle[1];
	}

	if (branch && branch.includes('--components--')) {
		return branch.replace(/.*--components--/, '');
	}

	return '';
}

/**
 * Newest version in a CHANGELOG body: the first `## [x.y.z` / `## x.y.z`
 * heading.
 *
 * @param {string} changelog CHANGELOG.md contents.
 * @return {string} Version, or empty string if no heading matches.
 */
function parseVersionFromChangelog(changelog) {
	for (const line of (changelog || '').split('\n')) {
		if (/^##\s+\[?[0-9]+\.[0-9]+\.[0-9]+/.test(line)) {
			return line.match(SEMVER)[0];
		}
	}
	return '';
}

/**
 * Version being released: first X.Y.Z in the PR title, else the newest
 * CHANGELOG heading for the component.
 *
 * @param {string} prTitle   Pull request title.
 * @param {string} component Component name.
 * @param {string} pluginsDir Directory holding plugin folders.
 * @return {string} Version, or empty string when none can be determined.
 */
function parseVersion(prTitle, component, pluginsDir) {
	const fromTitle = (prTitle || '').match(SEMVER);
	if (fromTitle) {
		return fromTitle[0];
	}

	if (!component) {
		return '';
	}

	const changelogPath = path.join(pluginsDir, component, 'CHANGELOG.md');
	if (fs.existsSync(changelogPath)) {
		return parseVersionFromChangelog(
			fs.readFileSync(changelogPath, 'utf8')
		);
	}

	return '';
}

/**
 * Resolve all three outputs for a release PR.
 *
 * @param {Object} opts
 * @param {string} opts.prTitle    Pull request title.
 * @param {string} opts.branch     Head branch name.
 * @param {string} [opts.pluginsDir] Directory holding plugin folders.
 * @return {{ version: string, component: string, plugin_dir: string }}
 */
function extractVersionInfo({ prTitle, branch, pluginsDir = 'plugins' }) {
	const component = parseComponent(prTitle, branch);
	const version = parseVersion(prTitle, component, pluginsDir);
	return {
		version,
		component,
		plugin_dir: `${pluginsDir}/${component}`,
	};
}

function main() {
	const args = parseArgs();
	const info = extractVersionInfo({
		prTitle: args['pr-title'] || '',
		branch: args.branch || '',
		pluginsDir: args['plugins-dir'] || 'plugins',
	});

	process.stdout.write(
		`version=${info.version}\n` +
			`component=${info.component}\n` +
			`plugin_dir=${info.plugin_dir}\n`
	);
	process.stderr.write(
		`Detected version: ${info.version} for component: ${info.component}\n`
	);
}

if (require.main === module) {
	main();
}

module.exports = {
	parseComponent,
	parseVersionFromChangelog,
	parseVersion,
	extractVersionInfo,
};
