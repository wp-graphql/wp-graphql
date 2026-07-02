const fs = require('fs');
const path = require('path');
const decodeHtmlEntities = require('../lib/decode-html-entities');

function parseFrontmatter(source) {
	const result = {};
	const lines = source.split('\n');
	let currentArrayKey = null;

	lines.forEach((line) => {
		const trimmed = line.trim();
		if (!trimmed || trimmed.startsWith('#')) {
			return;
		}

		if (trimmed.startsWith('- ') && currentArrayKey) {
			result[currentArrayKey].push(trimmed.replace(/^- /, '').trim());
			return;
		}

		const keyValueMatch = trimmed.match(/^([A-Za-z0-9_]+):\s*(.*)$/);
		if (!keyValueMatch) {
			currentArrayKey = null;
			return;
		}

		const key = keyValueMatch[1];
		const rawValue = keyValueMatch[2].trim();

		if (rawValue === '') {
			result[key] = [];
			currentArrayKey = key;
			return;
		}

		currentArrayKey = null;
		if (rawValue.startsWith('[') && rawValue.endsWith(']')) {
			result[key] = rawValue
				.slice(1, -1)
				.split(',')
				.map((item) => item.trim().replace(/^['"]|['"]$/g, ''))
				.filter(Boolean);
			return;
		}

		result[key] = rawValue.replace(/^['"]|['"]$/g, '');
	});

	return result;
}

function walkMarkdownFiles(entryPath, acc = []) {
	if (!fs.existsSync(entryPath)) {
		return acc;
	}

	const stat = fs.statSync(entryPath);
	if (stat.isFile() && entryPath.endsWith('.md')) {
		acc.push(entryPath);
		return acc;
	}

	if (!stat.isDirectory()) {
		return acc;
	}

	const entries = fs.readdirSync(entryPath);
	entries.forEach((entry) => {
		walkMarkdownFiles(path.join(entryPath, entry), acc);
	});

	return acc;
}

function normalizeUri(uri) {
	if (typeof uri !== 'string') {
		return '';
	}

	return uri.replace(/\/+$/, '').toLowerCase();
}

function toArray(value) {
	if (Array.isArray(value)) {
		return value.map((item) => String(item).trim()).filter(Boolean);
	}

	if (typeof value !== 'string') {
		return [];
	}

	const trimmed = value.trim();
	if (!trimmed) {
		return [];
	}

	return trimmed
		.split(',')
		.map((item) => item.trim())
		.filter(Boolean);
}

function escapeRegex(value) {
	return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function containsIdentifierMention(haystack, name) {
	if (!haystack || !name) {
		return false;
	}

	const esc = escapeRegex(name);
	const re = new RegExp(`(?:^|[^a-z0-9_])${esc}(?:[^a-z0-9_]|$)`, 'i');
	return re.test(haystack);
}

function uniqSorted(values) {
	return [...new Set(values)].sort((a, b) => a.localeCompare(b));
}

function mergeNameLists(explicit, inferred) {
	return uniqSorted([...explicit, ...inferred]);
}

function loadApiNameInventory(docsDir) {
	const hooksPath = path.join(docsDir, 'generated', 'hooks-index.json');
	const functionsPath = path.join(docsDir, 'generated', 'functions-index.json');

	const actions = [];
	const filters = [];

	if (fs.existsSync(hooksPath)) {
		const payload = JSON.parse(fs.readFileSync(hooksPath, 'utf8'));
		(payload.hooks || []).forEach((hook) => {
			if (!hook || hook.isDynamic) {
				return;
			}
			const name = hook.name;
			if (typeof name !== 'string' || !/^[a-z0-9_]+$/i.test(name)) {
				return;
			}
			if (hook.kind === 'action') {
				actions.push(name);
			} else if (hook.kind === 'filter') {
				filters.push(name);
			}
		});
	}

	const functions = [];
	if (fs.existsSync(functionsPath)) {
		const payload = JSON.parse(fs.readFileSync(functionsPath, 'utf8'));
		(payload.functions || []).forEach((fn) => {
			if (fn && typeof fn.name === 'string' && /^[a-z0-9_]+$/i.test(fn.name)) {
				functions.push(fn.name);
			}
		});
	}

	const longestFirst = (names) =>
		[...new Set(names)].sort((a, b) => {
			if (b.length !== a.length) {
				return b.length - a.length;
			}
			return a.localeCompare(b);
		});

	return {
		actions: longestFirst(actions),
		filters: longestFirst(filters),
		functions: longestFirst(functions),
	};
}

function inferRelatedApiNames(markdownBody, inventory) {
	const actions = [];
	inventory.actions.forEach((name) => {
		if (containsIdentifierMention(markdownBody, name)) {
			actions.push(name);
		}
	});

	const filters = [];
	inventory.filters.forEach((name) => {
		if (containsIdentifierMention(markdownBody, name)) {
			filters.push(name);
		}
	});

	const functions = [];
	inventory.functions.forEach((name) => {
		if (containsIdentifierMention(markdownBody, name)) {
			functions.push(name);
		}
	});

	return {
		actions,
		filters,
		functions,
	};
}

function stripLeadingGeneratedNotice(content) {
	if (typeof content !== 'string') {
		return '';
	}
	return content.replace(/^<!--[\s\S]*?-->\s*/m, '');
}

function stripFrontmatterBlock(content) {
	if (typeof content !== 'string') {
		return '';
	}
	const withoutNotice = stripLeadingGeneratedNotice(content);
	return withoutNotice.replace(/^---\n[\s\S]*?\n---\n?/, '\n');
}

function loadRecipeRelations(docsDir) {
	const recipesDir = path.join(docsDir, 'recipes');
	const files = walkMarkdownFiles(recipesDir, []).filter((filePath) => !filePath.endsWith('/index.md'));
	const inventory = loadApiNameInventory(docsDir);

	const recipes = files
		.map((filePath) => {
			const content = fs.readFileSync(filePath, 'utf8');
			const forFrontmatter = stripLeadingGeneratedNotice(content);
			const frontmatterMatch = forFrontmatter.match(/^---\n([\s\S]*?)\n---\n?/);
			const frontmatter = frontmatterMatch ? parseFrontmatter(frontmatterMatch[1]) : {};
			const bodyForInference = stripFrontmatterBlock(content);
			const inferred = inferRelatedApiNames(bodyForInference, inventory);
			const relativePath = path.relative(recipesDir, filePath).replace(/\\/g, '/');
			const slug = relativePath.replace(/\.md$/, '');
			const uri = `/recipes/${slug}`;
			const title =
				typeof frontmatter.title === 'string' && frontmatter.title.trim()
					? frontmatter.title.trim()
					: slug
							.split('/')
							.pop()
							.replace(/[-_]/g, ' ')
							.replace(/\b\w/g, (char) => char.toUpperCase());

			const wordpressUri =
				typeof frontmatter.wordpressUri === 'string' && frontmatter.wordpressUri.trim()
					? frontmatter.wordpressUri.trim()
					: '';

			return {
				title,
				slug,
				uri,
				wordpressUri,
				uriKey: normalizeUri(uri),
				group:
					typeof frontmatter.group === 'string' && frontmatter.group.trim()
						? frontmatter.group.trim()
						: 'Uncategorized',
				summary:
					typeof frontmatter.summary === 'string' && frontmatter.summary.trim()
						? decodeHtmlEntities(frontmatter.summary.trim())
						: '',
				relatedActions: mergeNameLists(toArray(frontmatter.relatedActions), inferred.actions),
				relatedFilters: mergeNameLists(toArray(frontmatter.relatedFilters), inferred.filters),
				relatedFunctions: mergeNameLists(toArray(frontmatter.relatedFunctions), inferred.functions),
			};
		})
		.sort((a, b) => a.title.localeCompare(b.title));

	const byAction = {};
	const byFilter = {};
	const byFunction = {};
	const byUri = {};

	recipes.forEach((recipe) => {
		byUri[recipe.uriKey] = recipe;
		if (recipe.wordpressUri) {
			byUri[normalizeUri(recipe.wordpressUri)] = recipe;
		}

		recipe.relatedActions.forEach((name) => {
			if (!byAction[name]) {
				byAction[name] = [];
			}
			byAction[name].push(recipe);
		});

		recipe.relatedFilters.forEach((name) => {
			if (!byFilter[name]) {
				byFilter[name] = [];
			}
			byFilter[name].push(recipe);
		});

		recipe.relatedFunctions.forEach((name) => {
			if (!byFunction[name]) {
				byFunction[name] = [];
			}
			byFunction[name].push(recipe);
		});
	});

	return {
		recipes,
		byAction,
		byFilter,
		byFunction,
		byUri,
	};
}

module.exports = {
	loadRecipeRelations,
	normalizeUri,
};
