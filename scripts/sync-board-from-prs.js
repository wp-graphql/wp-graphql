#!/usr/bin/env node

/**
 * Move work-queue board items to "In Progress" when a pull request that closes
 * them is open.
 *
 * The board tracks issues, not pull requests, so an issue sits in Up Next or
 * Planned while its fix is already written and in review. Nothing on the board
 * says so, and the only automatic transition is "Item closed" moving an issue
 * to Done once a PR merges. This fills that gap: an open PR carrying a closing
 * keyword is the strongest available signal that work on its issue has started.
 *
 * Scheduled rather than triggered on `pull_request` on purpose. Most
 * substantive PRs here come from forks, and a fork's `pull_request` run gets a
 * read-only token and no secrets, so it could not write to the project. The
 * alternative, `pull_request_target`, runs with secrets in the base repo's
 * context and is a well-known footgun; polling avoids that class entirely.
 *
 * Only issues in the monorepo are considered. A closing reference qualified to
 * another repository (`Closes owner/other-repo#12`) names an issue that does
 * not live on this board and is ignored.
 *
 * Usage:
 *   node scripts/sync-board-from-prs.js --project=<id> --status-field=<id> \
 *     --target-option=<id> [--from=Up Next,Planned,Inbox] [--dry-run]
 *
 * Options:
 *   --project        ProjectV2 node id. Required.
 *   --status-field   Status field node id. Required.
 *   --target-option  Single-select option id to move items to. Required.
 *   --from           Comma-separated statuses eligible to move. Items in any
 *                    other status (notably In Progress and Done) are left
 *                    alone. Default "Up Next,Planned,Inbox".
 *   --repo           owner/name of the repo whose PRs to read. Default
 *                    wp-graphql/wp-graphql.
 *   --prs            Path to a JSON array of {number, body, isDraft, author}
 *                    standing in for the live PR list, so tests run offline.
 *   --items          Path to a JSON array of board items standing in for the
 *                    live project, so tests run offline.
 *   --dry-run        Print what would change and write nothing.
 *
 * Exit codes: 0 on success or nothing to do. Non-zero only when a read or a
 * write fails unexpectedly. This job is best effort for a single item, but a
 * broken token or a renamed field is a real breakage and must not go green.
 */

const fs = require('fs');
const { execFileSync } = require('child_process');

const DEFAULT_REPO = 'wp-graphql/wp-graphql';
const DEFAULT_FROM = ['Up Next', 'Planned', 'Inbox'];

/**
 * GitHub's closing keywords, in every form it accepts: bare (`Closes #12`),
 * colon-separated (`Closes: #12`) and repository-qualified
 * (`Closes owner/repo#12`). Matching only `closes #N` misses the qualified
 * form, which is what cross-repo transfers leave behind.
 */
const CLOSING_RE =
	/\b(?:close[sd]?|fixe?[sd]?|resolve[sd]?)\s*:?\s+(?:([\w.-]+)\/([\w.-]+))?#(\d+)/gi;

function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		if (!arg.startsWith('--')) {
			return;
		}
		// Split on the first "=" only, so a value containing "=" survives.
		const eq = arg.indexOf('=');
		if (eq === -1) {
			args[arg.slice(2)] = true;
			return;
		}
		args[arg.slice(2, eq)] = arg.slice(eq + 1);
	});
	return args;
}

/**
 * Issue numbers a pull request body closes, limited to one repository.
 *
 * @param {string} body     The pull request body. Untrusted input: it is only
 *                          ever matched against, never interpolated anywhere.
 * @param {string} repoName The bare repo name that owns this board's issues.
 * @return {number[]} Issue numbers, de-duplicated, in first-seen order.
 */
function closingRefs(body, repoName) {
	const out = [];
	if (typeof body !== 'string') {
		return out;
	}
	// A /g regex carries lastIndex between calls, so use a fresh one.
	const re = new RegExp(CLOSING_RE.source, CLOSING_RE.flags);
	let m;
	while ((m = re.exec(body)) !== null) {
		const [, , refRepo, num] = m;
		if (refRepo && refRepo !== repoName) {
			continue;
		}
		const n = Number(num);
		if (!out.includes(n)) {
			out.push(n);
		}
	}
	return out;
}

/**
 * Decide which board items should move.
 *
 * Pure so the decision is testable without a network or a project.
 *
 * @param {Array<Object>} prs      Open pull requests: {number, body, isDraft, author}.
 * @param {Array<Object>} items    Board items: {id, status, number}.
 * @param {string}        repoName Bare repo name owning this board's issues.
 * @param {string[]}      from     Statuses eligible to move.
 * @return {Array<Object>} [{ itemId, issue, pr, fromStatus }]
 */
function planMoves(prs, items, repoName, from) {
	const byNumber = new Map();
	items.forEach((i) => {
		if (typeof i.number === 'number') {
			byNumber.set(i.number, i);
		}
	});

	const moves = [];
	const claimed = new Set();

	prs.forEach((pr) => {
		// A draft is not in review, and a bot's dependency bump never closes a
		// tracked issue, so neither is a signal that work started.
		if (pr.isDraft) {
			return;
		}
		if (isBot(pr.author)) {
			return;
		}
		closingRefs(pr.body, repoName).forEach((issue) => {
			const item = byNumber.get(issue);
			if (!item || claimed.has(issue)) {
				return;
			}
			if (!from.includes(item.status)) {
				return;
			}
			claimed.add(issue);
			moves.push({
				itemId: item.id,
				issue,
				pr: pr.number,
				fromStatus: item.status,
			});
		});
	});

	return moves;
}

/**
 * Whether a PR author is a bot. Dependabot opens the majority of PRs here and
 * never closes a tracked issue, so skipping bots keeps the job cheap and its
 * log readable.
 *
 * @param {string|Object} author The PR author, as a login or {login, is_bot}.
 * @return {boolean} True for a bot author.
 */
function isBot(author) {
	if (!author) {
		return false;
	}
	if (typeof author === 'object') {
		if (author.is_bot) {
			return true;
		}
		return isBot(author.login);
	}
	return /^(?:dependabot|github-actions|renovate)(?:\[bot\])?$/i.test(author);
}

/**
 * Run `gh` and parse its JSON output.
 *
 * execFileSync with an argument array, never a shell string, so nothing
 * derived from a pull request can reach a shell.
 *
 * @param {string[]} args Arguments to gh.
 * @return {*} Parsed JSON.
 */
function gh(args) {
	const out = execFileSync('gh', args, {
		encoding: 'utf8',
		maxBuffer: 32 * 1024 * 1024,
	});
	return JSON.parse(out);
}

/**
 * Open, non-draft pull requests for a repo.
 *
 * @param {string} repo owner/name.
 * @return {Array<Object>} PRs as {number, body, isDraft, author}.
 */
function fetchPrs(repo) {
	const rows = gh([
		'pr',
		'list',
		'-R',
		repo,
		'--state',
		'open',
		'--limit',
		'200',
		'--json',
		'number,body,isDraft,author',
	]);
	return rows.map((r) => ({
		number: r.number,
		body: r.body || '',
		isDraft: Boolean(r.isDraft),
		author: r.author && r.author.login,
	}));
}

/**
 * Board items with their current Status.
 *
 * @param {string} projectId ProjectV2 node id.
 * @return {Array<Object>} Items as {id, status, number}.
 */
function fetchItems(projectId) {
	const query = `
    query($id: ID!, $after: String) {
      node(id: $id) {
        ... on ProjectV2 {
          items(first: 100, after: $after) {
            pageInfo { hasNextPage endCursor }
            nodes {
              id
              fieldValueByName(name: "Status") {
                ... on ProjectV2ItemFieldSingleSelectValue { name }
              }
              content { ... on Issue { number repository { nameWithOwner } } }
            }
          }
        }
      }
    }`;

	const items = [];
	let after = null;
	// Bounded so a pageInfo bug cannot spin forever.
	for (let page = 0; page < 50; page++) {
		const args = ['api', 'graphql', '-f', `query=${query}`, '-F', `id=${projectId}`];
		if (after) {
			args.push('-F', `after=${after}`);
		}
		const res = gh(args);
		const conn = res.data.node.items;
		conn.nodes.forEach((n) => {
			items.push({
				id: n.id,
				status: n.fieldValueByName && n.fieldValueByName.name,
				number: n.content && n.content.number,
				repo: n.content && n.content.repository && n.content.repository.nameWithOwner,
			});
		});
		if (!conn.pageInfo.hasNextPage) {
			break;
		}
		after = conn.pageInfo.endCursor;
	}
	return items;
}

/**
 * Write one item's Status.
 *
 * @param {string} projectId  ProjectV2 node id.
 * @param {string} fieldId    Status field node id.
 * @param {string} itemId     Item node id.
 * @param {string} optionId   Single-select option id.
 */
function setStatus(projectId, fieldId, itemId, optionId) {
	const mutation = `
    mutation($p: ID!, $i: ID!, $f: ID!, $o: String!) {
      updateProjectV2ItemFieldValue(input: {
        projectId: $p, itemId: $i, fieldId: $f, value: { singleSelectOptionId: $o }
      }) { projectV2Item { id } }
    }`;
	gh([
		'api',
		'graphql',
		'-f',
		`query=${mutation}`,
		'-F',
		`p=${projectId}`,
		'-F',
		`i=${itemId}`,
		'-F',
		`f=${fieldId}`,
		'-F',
		`o=${optionId}`,
	]);
}

/**
 * Confirm the target option actually exists on the Status field.
 *
 * A mistyped option id is a configuration error, not a transient one, so it
 * fails the run rather than erroring once per write. Checked before anything
 * is written, and in --dry-run too, so a dry run can catch it.
 *
 * @param {string} fieldId  Status field node id.
 * @param {string} optionId Option id the run would write.
 * @return {string} The option's name, for the log.
 */
function assertOption(fieldId, optionId) {
	const query = `
    query($f: ID!) {
      node(id: $f) {
        ... on ProjectV2SingleSelectField { name options { id name } }
      }
    }`;
	const res = gh(['api', 'graphql', '-f', `query=${query}`, '-F', `f=${fieldId}`]);
	const field = res.data && res.data.node;
	if (!field || !Array.isArray(field.options)) {
		throw new Error(`field ${fieldId} is not a single-select field`);
	}
	const hit = field.options.find((o) => o.id === optionId);
	if (!hit) {
		const known = field.options.map((o) => `${o.id} (${o.name})`).join(', ');
		throw new Error(`option ${optionId} is not on field ${field.name}. Known: ${known}`);
	}
	return hit.name;
}

function main() {
	const args = parseArgs();
	const required = ['project', 'status-field', 'target-option'];
	const missing = required.filter((k) => !args[k]);
	if (missing.length) {
		console.error(`missing required: ${missing.map((m) => `--${m}`).join(', ')}`);
		process.exit(1);
	}

	const repo = args.repo || DEFAULT_REPO;
	const repoName = repo.split('/').pop();
	const from = args.from
		? String(args.from)
				.split(',')
				.map((s) => s.trim())
				.filter(Boolean)
		: DEFAULT_FROM;

	const prs = args.prs ? JSON.parse(fs.readFileSync(args.prs, 'utf8')) : fetchPrs(repo);
	const items = args.items
		? JSON.parse(fs.readFileSync(args.items, 'utf8'))
		: fetchItems(args.project);

	// Offline fixtures stand in for the project, so there is no field to check.
	if (!args.items) {
		const name = assertOption(args['status-field'], args['target-option']);
		console.log(`target status: ${name}`);
	}

	const moves = planMoves(prs, items, repoName, from);

	if (!moves.length) {
		console.log('nothing to move');
		return;
	}

	moves.forEach((mv) => {
		console.log(`#${mv.issue} ${mv.fromStatus} -> target (PR #${mv.pr})`);
		if (!args['dry-run']) {
			setStatus(args.project, args['status-field'], mv.itemId, args['target-option']);
		}
	});

	console.log(
		args['dry-run'] ? `dry run, ${moves.length} would move` : `moved ${moves.length}`
	);
}

if (require.main === module) {
	try {
		main();
	} catch (err) {
		// A bad token, a renamed field or a mistyped option id are all
		// configuration errors. Report them plainly and fail: going green while
		// writing nothing would hide a board that silently stopped syncing.
		console.error(`sync-board-from-prs: ${err.message}`);
		process.exit(1);
	}
}

module.exports = { closingRefs, planMoves, isBot, parseArgs, assertOption };
