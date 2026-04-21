#!/usr/bin/env node

/**
 * One-way migration: WordPress CodeSnippet (recipes) → markdown in plugins/<plugin>/docs/recipes/.
 *
 * Uses the same archive shape as wpgraphql.com (nodeByUri → ContentType → contentNodes).
 *
 * Environment:
 *   WPGRAPHQL_URL — required unless passed as --endpoint=
 *
 * Optional: loads websites/wpgraphql.com/.env.local if present (KEY=value lines).
 *
 * Usage:
 *   node scripts/recipes/migrate-recipes-from-wp.js --plugin=wp-graphql
 *   node scripts/recipes/migrate-recipes-from-wp.js --plugin=wp-graphql --dry-run=true
 *   node scripts/recipes/migrate-recipes-from-wp.js --plugin=wp-graphql --archive-uri=/recipes --force=true
 */

const fs = require('fs');
const path = require('path');
const decodeHtmlEntities = require('../lib/decode-html-entities');

const MIGRATED_NOTICE = [
	'<!--',
	'Migrated from WordPress (CodeSnippet).',
	'Edit freely. Re-run migrate with --force=true to overwrite this file.',
	'-->',
	'',
].join('\n');

function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		const [rawKey, ...valueParts] = arg.replace(/^--/, '').split('=');
		const value = valueParts.join('=');
		args[rawKey] = value === '' ? true : value;
	});
	return args;
}

function readJson(filePath) {
	if (!fs.existsSync(filePath)) {
		throw new Error(`File not found: ${filePath}`);
	}
	return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function ensureDir(dirPath) {
	if (!fs.existsSync(dirPath)) {
		fs.mkdirSync(dirPath, { recursive: true });
	}
}

function loadEnvFile(filePath) {
	if (!fs.existsSync(filePath)) {
		return;
	}
	fs.readFileSync(filePath, 'utf8')
		.split('\n')
		.forEach((line) => {
			const trimmed = line.trim();
			if (!trimmed || trimmed.startsWith('#')) {
				return;
			}
			const match = trimmed.match(/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/);
			if (!match) {
				return;
			}
			const key = match[1];
			if (process.env[key] !== undefined) {
				return;
			}
			let value = match[2].trim();
			if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
				value = value.slice(1, -1);
			}
			process.env[key] = value;
		});
}

function toPlainText(html) {
	return String(html ?? '')
		.replace(/<[^>]*>/g, ' ')
		.replace(/&nbsp;/gi, ' ')
		.replace(/&amp;/gi, '&')
		.replace(/&lt;/gi, '<')
		.replace(/&gt;/gi, '>')
		.replace(/\s+/g, ' ')
		.trim();
}

function excerptFromHtml(html, maxLength = 220) {
	const text = decodeHtmlEntities(toPlainText(html));
	if (text.length <= maxLength) {
		return text;
	}
	return `${text.slice(0, maxLength).trim()}…`;
}

function slugFromWpUri(uri) {
	const parts = String(uri || '')
		.replace(/\/+$/, '')
		.split('/')
		.filter(Boolean);
	const last = parts[parts.length - 1] || 'recipe';
	return last
		.toLowerCase()
		.replace(/[^a-z0-9-]+/g, '-')
		.replace(/-+/g, '-')
		.replace(/^-|-$/g, '') || 'recipe';
}

function yamlScalar(value) {
	if (value === null || value === undefined) {
		return '""';
	}
	if (typeof value === 'number') {
		return String(value);
	}
	return JSON.stringify(String(value));
}

function createTurndown() {
	try {
		const TurndownService = require('turndown');
		return new TurndownService({
			headingStyle: 'atx',
			codeBlockStyle: 'fenced',
			bulletListMarker: '-',
		});
	} catch {
		throw new Error(
			'Missing dependency "turndown". Install it from the repo root:\n  npm install -D turndown'
		);
	}
}

async function sleep(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

async function graphqlRequest(endpoint, query, variables, attempt = 1) {
	const res = await fetch(endpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({ query, variables }),
	});

	if (res.status === 429 && attempt < 5) {
		const retryAfter = Number(res.headers.get('retry-after')) || attempt * 2;
		await sleep(retryAfter * 1000);
		return graphqlRequest(endpoint, query, variables, attempt + 1);
	}

	const text = await res.text();
	let json;
	try {
		json = JSON.parse(text);
	} catch {
		throw new Error(`GraphQL response not JSON (HTTP ${res.status}): ${text.slice(0, 400)}`);
	}

	if (!res.ok) {
		throw new Error(`GraphQL HTTP ${res.status}: ${text.slice(0, 400)}`);
	}

	if (json.errors && json.errors.length) {
		const msg = json.errors.map((e) => e.message).join('; ');
		throw new Error(`GraphQL errors: ${msg}`);
	}

	return json.data;
}

const RECIPE_ARCHIVE_QUERY = `
query RecipeArchivePage($uri: String!, $first: Int!, $after: String) {
  archive: nodeByUri(uri: $uri) {
    __typename
    ... on ContentType {
      uri
      label
      contentNodes(first: $first, after: $after) {
        pageInfo {
          hasNextPage
          endCursor
        }
        nodes {
          __typename
          ... on CodeSnippet {
            databaseId
            uri
            title
            content(format: RENDERED)
            codeSnippetTags {
              nodes {
                name
              }
            }
          }
        }
      }
    }
  }
}
`;

function renderRecipeMarkdown({ title, wordpressUri, wordpressId, group, summary, bodyMarkdown }) {
	const lines = [];
	lines.push(MIGRATED_NOTICE.trimEnd());
	lines.push('---');
	lines.push(`title: ${yamlScalar(title)}`);
	lines.push(`wordpressUri: ${yamlScalar(wordpressUri)}`);
	lines.push(`wordpressId: ${yamlScalar(String(wordpressId))}`);
	lines.push(`group: ${yamlScalar(group)}`);
	lines.push(`summary: ${yamlScalar(summary)}`);
	lines.push('---');
	lines.push('');
	lines.push(bodyMarkdown.trim());
	lines.push('');
	return lines.join('\n');
}

async function fetchAllRecipes({ endpoint, archiveUri, pageSize }) {
	const td = createTurndown();
	const recipes = [];
	let after = null;
	let hasNext = true;

	while (hasNext) {
		const data = await graphqlRequest(endpoint, RECIPE_ARCHIVE_QUERY, {
			uri: archiveUri,
			first: pageSize,
			after,
		});

		const archive = data?.archive;
		if (!archive || archive.__typename !== 'ContentType') {
			throw new Error(
				`Archive node not found or not a ContentType for uri "${archiveUri}". ` +
					`Check --archive-uri= (try /recipes) and WPGRAPHQL_URL.`
			);
		}

		const conn = archive.contentNodes;
		const nodes = conn?.nodes || [];
		nodes.forEach((node) => {
			if (!node || node.__typename !== 'CodeSnippet') {
				return;
			}
			const tagName = node.codeSnippetTags?.nodes?.[0]?.name || '';
			const group = tagName || 'Uncategorized';
			const summary = excerptFromHtml(node.content);
			const bodyMarkdown = td.turndown(node.content || '');
			recipes.push({
				databaseId: node.databaseId,
				uri: node.uri,
				title: node.title || slugFromWpUri(node.uri),
				group,
				summary,
				bodyMarkdown,
			});
		});

		hasNext = Boolean(conn?.pageInfo?.hasNextPage);
		after = conn?.pageInfo?.endCursor || null;
	}

	return recipes;
}

function main() {
	const args = parseArgs();
	const repoRoot = process.cwd();
	const pluginSlug = args.plugin;
	const dryRun = args['dry-run'] === 'true' || args['dry-run'] === true;
	const force = args.force === 'true' || args.force === true;
	const archiveUri = typeof args['archive-uri'] === 'string' ? args['archive-uri'] : '/recipes';
	const pageSize = Number(args['page-size'] || 50) || 50;
	const endpoint =
		typeof args.endpoint === 'string' && args.endpoint.trim()
			? args.endpoint.trim()
			: process.env.WPGRAPHQL_URL;

	loadEnvFile(path.join(repoRoot, 'websites', 'wpgraphql.com', '.env.local'));

	if (!pluginSlug) {
		console.error('Error: --plugin is required');
		console.error(
			'Usage: node scripts/recipes/migrate-recipes-from-wp.js --plugin=wp-graphql [--dry-run=true] [--force=true] [--archive-uri=/recipes] [--endpoint=$WPGRAPHQL_URL]'
		);
		process.exit(1);
	}

	if (!endpoint) {
		console.error('Error: WPGRAPHQL_URL is not set and --endpoint= was not provided.');
		process.exit(1);
	}

	const configPath = args.config
		? path.resolve(repoRoot, args.config)
		: path.resolve(repoRoot, 'scripts/hooks/plugin-config.json');
	const pluginConfigMap = readJson(configPath);
	const pluginConfig = pluginConfigMap[pluginSlug];
	if (!pluginConfig) {
		console.error(`Error: Plugin "${pluginSlug}" not found in ${configPath}`);
		process.exit(1);
	}

	const docsDir = path.resolve(repoRoot, pluginConfig.docsDir);
	const recipesDir = path.join(docsDir, 'recipes');
	ensureDir(recipesDir);

	(async () => {
		console.log(`Fetching recipes from ${endpoint} (archive uri: ${archiveUri})…`);
		const recipes = await fetchAllRecipes({ endpoint, archiveUri, pageSize });
		console.log(`Found ${recipes.length} recipe(s).`);

		const usedSlugs = new Set();
		let written = 0;
		let skipped = 0;

		recipes.forEach((recipe) => {
			let slug = slugFromWpUri(recipe.uri);
			if (usedSlugs.has(slug)) {
				slug = `${slug}-${recipe.databaseId}`;
			}
			usedSlugs.add(slug);

			const outPath = path.join(recipesDir, `${slug}.md`);
			if (fs.existsSync(outPath) && !force) {
				console.log(`skip (exists): ${path.relative(repoRoot, outPath)}`);
				skipped += 1;
				return;
			}

			const md = renderRecipeMarkdown({
				title: recipe.title,
				wordpressUri: recipe.uri,
				wordpressId: recipe.databaseId,
				group: recipe.group,
				summary: recipe.summary,
				bodyMarkdown: recipe.bodyMarkdown,
			});

			if (dryRun) {
				console.log(`dry-run: would write ${path.relative(repoRoot, outPath)}`);
				written += 1;
				return;
			}

			fs.writeFileSync(outPath, md, 'utf8');
			console.log(`wrote ${path.relative(repoRoot, outPath)}`);
			written += 1;
		});

		console.log(`Done. wrote=${written}, skipped=${skipped}, dryRun=${dryRun}`);
		if (!dryRun && written > 0) {
			console.log('Next: npm run recipes:generate -- --plugin=wp-graphql');
		}
	})().catch((err) => {
		console.error(err);
		process.exit(1);
	});
}

if (require.main === module) {
	main();
}
