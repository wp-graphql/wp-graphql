#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { loadRecipeRelations } = require('./recipe-relations');

const TEST_ROOT = path.join(__dirname, '..', '..', '.test-recipe-relations');

function setup() {
	if (fs.existsSync(TEST_ROOT)) {
		fs.rmSync(TEST_ROOT, { recursive: true });
	}

	const docsDir = path.join(TEST_ROOT, 'docs');
	fs.mkdirSync(path.join(docsDir, 'recipes'), { recursive: true });
	fs.mkdirSync(path.join(docsDir, 'generated'), { recursive: true });

	fs.writeFileSync(
		path.join(docsDir, 'generated', 'hooks-index.json'),
		JSON.stringify({
			hooks: [
				{ name: 'graphql_register_types', kind: 'action', isDynamic: false },
				{ name: 'graphql_some_dynamic', kind: 'action', isDynamic: true },
				{ name: 'graphql_connection_query_args', kind: 'filter', isDynamic: false },
			],
		}),
		'utf8'
	);

	fs.writeFileSync(
		path.join(docsDir, 'generated', 'functions-index.json'),
		JSON.stringify({
			functions: [{ name: 'register_graphql_field' }, { name: 'graphql' }],
		}),
		'utf8'
	);

	fs.writeFileSync(
		path.join(docsDir, 'recipes', 'sample.md'),
		[
			'<!--',
			'Notice before frontmatter (migrated recipes).',
			'-->',
			'',
			'---',
			'title: Sample recipe',
			'relatedActions:',
			'  - graphql_execute',
			'relatedFunctions:',
			'  - graphql',
			'---',
			'',
			'Call register_graphql_field from graphql_register_types.',
			'Also mention graphql_connection_query_args in prose.',
			'',
		].join('\n'),
		'utf8'
	);
}

function cleanup() {
	if (fs.existsSync(TEST_ROOT)) {
		fs.rmSync(TEST_ROOT, { recursive: true });
	}
}

try {
	setup();
	const { recipes, byAction, byFilter, byFunction } = loadRecipeRelations(path.join(TEST_ROOT, 'docs'));
	assert.strictEqual(recipes.length, 1);
	const recipe = recipes[0];

	assert.deepStrictEqual(recipe.relatedActions.sort(), ['graphql_execute', 'graphql_register_types'].sort());
	assert.deepStrictEqual(recipe.relatedFilters, ['graphql_connection_query_args']);
	assert.deepStrictEqual(recipe.relatedFunctions.sort(), ['graphql', 'register_graphql_field'].sort());

	assert.ok(byAction.graphql_register_types.some((r) => r.slug === 'sample'));
	assert.ok(byFilter.graphql_connection_query_args.some((r) => r.slug === 'sample'));
	assert.ok(byFunction.register_graphql_field.some((r) => r.slug === 'sample'));

	// Dynamic hook must not be inferred from body text.
	assert.strictEqual(byAction.graphql_some_dynamic, undefined);

	fs.writeFileSync(
		path.join(TEST_ROOT, 'docs', 'recipes', 'boundary.md'),
		['---', 'title: Boundary', '---', '', 'Only graphql_register_types here.', ''].join('\n'),
		'utf8'
	);
	const rel2 = loadRecipeRelations(path.join(TEST_ROOT, 'docs'));
	const boundary = rel2.recipes.find((r) => r.slug === 'boundary');
	assert.deepStrictEqual(boundary.relatedActions, ['graphql_register_types']);
	assert.deepStrictEqual(boundary.relatedFunctions, []);

	console.log('recipe-relations.test.js: ok');
} finally {
	cleanup();
}
