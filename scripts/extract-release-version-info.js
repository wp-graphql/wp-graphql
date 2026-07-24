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
 * workflow can append them to $GITHUB_OUTPUT. Inputs come from the PR_TITLE
 * and BRANCH_NAME env vars, so the workflow never builds a command out of
 * PR-controlled strings.
 *
 * Usage:
 *   PR_TITLE="$PR_TITLE" BRANCH_NAME="$BRANCH_NAME" \
 *     node scripts/extract-release-version-info.js >> "$GITHUB_OUTPUT"
 *
 * Options (override the env vars; mainly for tests and local runs):
 *   --pr-title     Pull request title.
 *   --branch       Head branch name.
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
 * When the branch has no "--components--" marker, the whole branch name is
 * returned unchanged — matching the original `sed 's/.*--components--//'`.
 * That is deliberate: a bogus-but-non-empty component points downstream steps
 * at a non-existent `plugins/<branch>` dir (fail fast), whereas an empty
 * component would resolve to `plugins/` and risk operating on every plugin.
 *
 * @param {string} prTitle Pull request title.
 * @param {string} branch  Head branch name.
 * @return {string} Component (empty only when there is no title match and no branch).
 */
function parseComponent(prTitle, branch) {
	const fromTitle = (prTitle || '').match(/release\s+([a-z0-9-]+)/);
	if (fromTitle) {
		return fromTitle[1];
	}

	if (branch) {
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
	// Prefer env vars (PR_TITLE / BRANCH_NAME) so the workflow never has to
	// build a shell command out of PR-controlled strings — the CLI flags stay
	// available as an override for tests and local runs.
	const info = extractVersionInfo({
		prTitle: args['pr-title'] || process.env.PR_TITLE || '',
		branch: args.branch || process.env.BRANCH_NAME || '',
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
