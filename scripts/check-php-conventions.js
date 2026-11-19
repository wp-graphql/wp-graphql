#!/usr/bin/env node
/**
 * Checks the PHP a change adds to the plugins for two conventions that the other
 * linters don't cover:
 *
 * - New functions, classes, interfaces, traits, enums, `const` constants and
 *   non-private methods have a docblock with an `@since` tag (`@since x-release-please-version`
 *   for new code). An `@inheritDoc` docblock is accepted for overrides.
 * - User-facing strings are translated. A literal string as the value of a
 *   user-facing key (`label`, `desc`, `description`, `title`, `placeholder`,
 *   `instructions`, `message`), or as a value in an `options` array, is flagged.
 *   PHPCS's WordPress.WP.I18n rule only checks calls that are already wrapped in a
 *   translation function, so it can't see these.
 *
 * Only declarations and lines the change adds are checked, so existing code that
 * predates the conventions doesn't fail. A line can opt out of the translation
 * check with an `i18n-ignore` comment, for a string that deliberately isn't
 * translated.
 *
 * It checks `plugins/<plugin>/src/**.php` and `plugins/<plugin>/access-functions.php`.
 *
 * Usage:
 *   node scripts/check-php-conventions.js [--base-ref=<ref>] [--all]
 *
 *   --base-ref  The ref to compare against. Defaults to the pull request's base
 *               branch, the commit before a push, or origin/main.
 *   --all       Check every line of every matching file for untranslated strings,
 *               instead of only what the change adds. Reports, but always exits 0,
 *               for taking inventory. It skips the @since check, since most existing
 *               declarations predate that convention.
 */

const { execFileSync } = require('child_process');
const fs = require('fs');

const USER_FACING_KEYS = [
	'label',
	'desc',
	'description',
	'title',
	'placeholder',
	'instructions',
	'message',
];

const PATH_PATTERN = /^plugins\/[^/]+\/(src\/.+\.php|access-functions\.php)$/;

/**
 * Parses `--key=value` arguments, splitting on the first `=` only.
 *
 * @param {string[]} argv The arguments.
 * @return {Object<string,string|boolean>} The parsed arguments.
 */
function parseArgs(argv = process.argv.slice(2)) {
	return argv.reduce((acc, arg) => {
		if (!arg.startsWith('--')) {
			return acc;
		}
		const body = arg.slice(2);
		const index = body.indexOf('=');
		if (-1 === index) {
			acc[body] = true;
		} else {
			acc[body.slice(0, index)] = body.slice(index + 1);
		}
		return acc;
	}, {});
}

/**
 * The ref to compare against when none is given, from the GitHub Actions environment.
 *
 * @param {Object<string,string|undefined>} env The environment.
 * @return {string} The ref.
 */
function getDefaultBaseRef(env = process.env) {
	if ('pull_request' === env.GITHUB_EVENT_NAME && env.GITHUB_BASE_REF) {
		return `origin/${env.GITHUB_BASE_REF}`;
	}
	if (
		'push' === env.GITHUB_EVENT_NAME &&
		env.EVENT_BEFORE &&
		!/^0+$/.test(env.EVENT_BEFORE)
	) {
		return env.EVENT_BEFORE;
	}
	return 'origin/main';
}

/**
 * Whether a path is one this script checks.
 *
 * @param {string} filePath A path from the repository root.
 * @return {boolean} Whether to check it.
 */
function isCheckedPath(filePath) {
	return PATH_PATTERN.test(filePath) && !filePath.includes('/vendor/');
}

/**
 * Replaces the contents of comments, string literals and inline HTML (between `?>`
 * and the next `<?php`) with spaces, keeping line breaks and quote characters, so
 * declarations and brackets can be matched without being fooled by text. Docblocks
 * are kept, since `@since` is read from them. Heredocs aren't recognised.
 *
 * @param {string} source PHP source.
 * @return {string} The source with comment and string contents blanked.
 */
function blankStringsAndComments(source) {
	let out = '';
	let i = 0;
	while (i < source.length) {
		const ch = source[i];
		const next = source[i + 1];
		if ('?' === ch && '>' === next) {
			const reopen = source.slice(i).search(/<\?(php|=)/);
			const stop = -1 === reopen ? source.length : i + reopen;
			out += source.slice(i, stop).replace(/[^\n]/g, ' ');
			i = stop;
		} else if ('/' === ch && '*' === next) {
			const end = source.indexOf('*/', i + 2);
			const stop = -1 === end ? source.length : end + 2;
			const comment = source.slice(i, stop);
			// Keep docblocks, blank other block comments.
			out += comment.startsWith('/**')
				? comment
				: comment.replace(/[^\n]/g, ' ');
			i = stop;
		} else if (
			('/' === ch && '/' === next) ||
			('#' === ch && '[' !== next)
		) {
			const end = source.indexOf('\n', i);
			const stop = -1 === end ? source.length : end;
			out += ' '.repeat(stop - i);
			i = stop;
		} else if ("'" === ch || '"' === ch) {
			let j = i + 1;
			while (j < source.length && source[j] !== ch) {
				j += '\\' === source[j] ? 2 : 1;
			}
			out +=
				ch +
				source.slice(i + 1, j).replace(/[^\n]/g, ' ') +
				(j < source.length ? ch : '');
			i = j + 1;
		} else {
			out += ch;
			i++;
		}
	}
	return out;
}

/**
 * The named declarations in a PHP source that need an `@since` tag: functions,
 * classes, interfaces, traits, enums, constants and methods that aren't private.
 *
 * @param {string} source PHP source.
 * @return {{kind: string, name: string, line: number, key: string}[]} The declarations, with 1-based lines.
 */
function extractDeclarations(source) {
	const lines = blankStringsAndComments(source).split('\n');
	const declarations = [];
	const pattern =
		/^\s*(?:(?:final|abstract|readonly)\s+)*(?:(public|protected|private)\s+)?(?:(?:static|final|abstract)\s+)*(function|const|class|interface|trait|enum)\s+&?([A-Za-z_]\w*)/;

	lines.forEach((line, index) => {
		const match = line.match(pattern);
		if (!match || 'private' === match[1]) {
			return;
		}
		const kind = 'const' === match[2] ? 'const' : match[2];
		declarations.push({
			kind,
			name: match[3],
			line: index + 1,
			key: `${kind}:${match[3]}`,
		});
	});

	return declarations;
}

/**
 * The docblock directly above a line, skipping attributes and blank lines.
 *
 * @param {string[]} lines     The source's lines.
 * @param {number}   lineIndex The 0-based index of the declaration line.
 * @return {string|null} The docblock, or null when there is none.
 */
function docblockAbove(lines, lineIndex) {
	let j = lineIndex - 1;
	while (
		j >= 0 &&
		('' === lines[j].trim() || lines[j].trim().startsWith('#['))
	) {
		j--;
	}
	if (j < 0 || !lines[j].trim().endsWith('*/')) {
		return null;
	}
	const end = j;
	while (j >= 0 && !lines[j].trim().startsWith('/**')) {
		if (lines[j].trim().startsWith('/*') && j !== end) {
			return null;
		}
		j--;
	}
	return j < 0 ? null : lines.slice(j, end + 1).join('\n');
}

/**
 * The declarations in the head source that aren't in the base source and have no
 * `@since` tag. Declarations are compared by kind and name, counted, so a second
 * method with an existing name in a file is still new.
 *
 * @param {string} baseSource The file before the change, or '' for a new file.
 * @param {string} headSource The file after the change.
 * @return {{line: number, message: string}[]} The problems found.
 */
function findMissingSince(baseSource, headSource) {
	const remaining = {};
	extractDeclarations(baseSource).forEach(({ key }) => {
		remaining[key] = (remaining[key] || 0) + 1;
	});

	const lines = headSource.split('\n');
	const problems = [];
	extractDeclarations(headSource).forEach(({ kind, name, line, key }) => {
		if (remaining[key]) {
			remaining[key]--;
			return;
		}
		const docblock = docblockAbove(lines, line - 1);
		if (docblock && /@since\b|@inheritDoc\b/i.test(docblock)) {
			return;
		}
		problems.push({
			line,
			message: `New ${kind} "${name}" needs a docblock with @since x-release-please-version.`,
		});
	});

	return problems;
}

/**
 * The 1-based line ranges of `options` arrays in a source.
 *
 * @param {string} blanked Source with strings and comments blanked.
 * @param {string} source  The original source, to read the key names.
 * @return {{start: number, end: number}[]} The ranges, inclusive.
 */
function optionsArrayRanges(blanked, source) {
	const ranges = [];
	const keyPattern = /(['"])options\1\s*=>\s*(\[|array\s*\()/g;
	let match;
	while ((match = keyPattern.exec(source))) {
		// Skip matches inside strings or comments.
		if (
			blanked.slice(match.index, match.index + match[0].length) !==
			match[0].replace(/options/, '       ')
		) {
			continue;
		}
		const open = match.index + match[0].length - 1;
		const closeChar = '[' === blanked[open] ? ']' : ')';
		const openChar = blanked[open];
		let depth = 0;
		let k = open;
		for (; k < blanked.length; k++) {
			if (blanked[k] === openChar) {
				depth++;
			} else if (blanked[k] === closeChar) {
				depth--;
				if (0 === depth) {
					break;
				}
			}
		}
		const lineOf = (offset) => source.slice(0, offset).split('\n').length;
		ranges.push({ start: lineOf(open), end: lineOf(k) });
	}
	return ranges;
}

/**
 * Lines with a user-facing string that isn't translated.
 *
 * @param {string}           headSource The file after the change.
 * @param {Set<number>|null} lineNumbers The 1-based lines to check, or null for every line.
 * @return {{line: number, message: string}[]} The problems found.
 */
function findUntranslatedStrings(headSource, lineNumbers) {
	const lines = headSource.split('\n');
	const blankedLines = blankStringsAndComments(headSource).split('\n');
	const ranges = optionsArrayRanges(blankedLines.join('\n'), headSource);
	const keyPattern = new RegExp(
		`(['"])(${USER_FACING_KEYS.join('|')})\\1\\s*=>\\s*['"][A-Za-z]`
	);
	const valuePattern = /=>\s*['"][A-Za-z]/;
	const problems = [];

	lines.forEach((line, index) => {
		const lineNumber = index + 1;
		if (
			(lineNumbers && !lineNumbers.has(lineNumber)) ||
			line.includes('i18n-ignore')
		) {
			return;
		}
		// Only consider code, not a match inside a comment.
		if (
			'' === blankedLines[index].replace(/['"\s]/g, '') ||
			/^\s*(\*|\/\/|#(?!\[))/.test(line)
		) {
			return;
		}
		const keyMatch = line.match(keyPattern);
		// The key's opening quote must survive blanking, or the match is inside a string.
		if (keyMatch && blankedLines[index][keyMatch.index] === keyMatch[1]) {
			problems.push({
				line: lineNumber,
				message: `The "${keyMatch[2]}" value is shown to users, so wrap it in __() (or esc_html__() and the like) with the plugin's text domain.`,
			});
			return;
		}
		const inOptions = ranges.some(
			({ start, end }) => lineNumber > start && lineNumber <= end
		);
		const valueMatch = inOptions ? line.match(valuePattern) : null;
		const quoteIndex = valueMatch
			? valueMatch.index + valueMatch[0].length - 2
			: -1;
		if (valueMatch && /['"]/.test(blankedLines[index][quoteIndex] || '')) {
			problems.push({
				line: lineNumber,
				message:
					"Option labels are shown to users, so wrap this one in __() (or esc_html__() and the like) with the plugin's text domain.",
			});
		}
	});

	return problems;
}

/**
 * Checks one file.
 *
 * @param {{path: string, baseSource: string, headSource: string, addedLines: Set<number>|null}} file
 *        The file, its source before and after the change, and the lines the change
 *        added (null to check every line for untranslated strings only).
 * @return {{path: string, line: number, message: string}[]} The problems found.
 */
function checkFile({ path: filePath, baseSource, headSource, addedLines }) {
	// Every line is only checked for strings: most existing declarations predate @since.
	const since =
		null === addedLines
			? []
			: findMissingSince(baseSource, headSource).filter(({ line }) =>
					addedLines.has(line)
				);
	const strings = findUntranslatedStrings(headSource, addedLines);

	return [...since, ...strings]
		.sort((a, b) => a.line - b.line)
		.map((problem) => ({ path: filePath, ...problem }));
}

/**
 * Parses the added line numbers from `git diff -U0` output for one file.
 *
 * @param {string} diff The diff.
 * @return {Set<number>} The 1-based line numbers added in the new file.
 */
function parseAddedLines(diff) {
	const added = new Set();
	const hunk = /^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/gm;
	let match;
	while ((match = hunk.exec(diff))) {
		const start = Number(match[1]);
		const count = undefined === match[2] ? 1 : Number(match[2]);
		for (let n = start; n < start + count; n++) {
			added.add(n);
		}
	}
	return added;
}

/**
 * Parses `git diff --name-status -M -z` output into the files to check. With `-z`,
 * fields are NUL-separated and paths are never quoted, whatever characters they hold.
 *
 * @param {string} output The output.
 * @return {{path: string, basePath: string|null}[]} Added, modified and renamed files.
 */
function parseNameStatus(output) {
	const fields = output.split('\0');
	const files = [];
	let i = 0;
	while (i < fields.length && fields[i]) {
		const status = fields[i];
		if (status.startsWith('R') || status.startsWith('C')) {
			files.push(
				status.startsWith('R')
					? { path: fields[i + 2], basePath: fields[i + 1] }
					: { path: fields[i + 2], basePath: null }
			);
			i += 3;
			continue;
		}
		if ('A' === status) {
			files.push({ path: fields[i + 1], basePath: null });
		} else if ('M' === status) {
			files.push({ path: fields[i + 1], basePath: fields[i + 1] });
		}
		i += 2;
	}
	return files.filter((file) => isCheckedPath(file.path));
}

/**
 * Escapes a value for a GitHub Actions workflow command.
 *
 * @param {string}  value    The value.
 * @param {boolean} property Whether it's a property (file, line) rather than the message.
 * @return {string} The escaped value.
 */
function escapeWorkflowCommand(value, property = false) {
	let escaped = String(value)
		.replace(/%/g, '%25')
		.replace(/\r/g, '%0D')
		.replace(/\n/g, '%0A');
	if (property) {
		escaped = escaped.replace(/:/g, '%3A').replace(/,/g, '%2C');
	}
	return escaped;
}

function git(args) {
	// execFileSync passes each argument as its own argv entry with no shell, so refs
	// and paths can't be read as shell syntax.
	return execFileSync('git', args, {
		encoding: 'utf8',
		maxBuffer: 64 * 1024 * 1024,
	});
}

function readAtRef(ref, filePath) {
	try {
		return git(['show', `${ref}:${filePath}`]);
	} catch {
		return '';
	}
}

function listAllFiles() {
	return git(['ls-files', 'plugins'])
		.split('\n')
		.filter((filePath) => filePath && isCheckedPath(filePath));
}

function main() {
	const args = parseArgs();
	const all = true === args.all;
	let problems = [];

	if (all) {
		listAllFiles().forEach((filePath) => {
			const headSource = fs.readFileSync(filePath, 'utf8');
			problems = problems.concat(
				checkFile({
					path: filePath,
					baseSource: '',
					headSource,
					addedLines: null,
				})
			);
		});
	} else {
		const baseRef =
			typeof args['base-ref'] === 'string' && args['base-ref']
				? args['base-ref']
				: getDefaultBaseRef();
		let files;
		try {
			files = parseNameStatus(
				git(['diff', '--name-status', '-M', '-z', `${baseRef}...HEAD`])
			);
		} catch (error) {
			// This check must not pass silently when it can't see the change.
			console.error(
				`Could not diff against ${baseRef}: ${error.message}`
			);
			process.exit(1);
		}

		if (0 === files.length) {
			console.log(`No plugin PHP files changed against ${baseRef}.`);
			return;
		}

		files.forEach(({ path: filePath, basePath }) => {
			const headSource = fs.readFileSync(filePath, 'utf8');
			const baseSource = basePath ? readAtRef(baseRef, basePath) : '';
			// Both paths, so git still pairs a renamed file with its old version and only
			// the lines that changed count as added.
			const paths =
				basePath && basePath !== filePath
					? [basePath, filePath]
					: [filePath];
			const addedLines = parseAddedLines(
				git(['diff', '-U0', '-M', `${baseRef}...HEAD`, '--', ...paths])
			);
			problems = problems.concat(
				checkFile({
					path: filePath,
					baseSource,
					headSource,
					addedLines,
				})
			);
		});
	}

	problems.forEach(({ path: filePath, line, message }) => {
		if (process.env.GITHUB_ACTIONS && !all) {
			console.log(
				`::error file=${escapeWorkflowCommand(filePath, true)},line=${line}::${escapeWorkflowCommand(message)}`
			);
		} else {
			console.log(`${filePath}:${line}: ${message}`);
		}
	});

	if (0 === problems.length) {
		console.log(
			all
				? 'No problems found.'
				: 'No problems found in the lines this change adds.'
		);
		return;
	}

	console.log(`\n${problems.length} problem(s) found.`);
	if (!all) {
		process.exit(1);
	}
}

if (require.main === module) {
	main();
}

module.exports = {
	parseArgs,
	getDefaultBaseRef,
	isCheckedPath,
	blankStringsAndComments,
	extractDeclarations,
	docblockAbove,
	findMissingSince,
	findUntranslatedStrings,
	checkFile,
	parseAddedLines,
	parseNameStatus,
	escapeWorkflowCommand,
};
