#!/usr/bin/env node

const assert = require('assert');
const { escapeMdxText, renderRecipesIndex } = require('./generate-recipe-docs');

function run() {
	assert.strictEqual(
		escapeMdxText('function() { return [ "a" => 1 ]; }'),
		'function() \\{ return [ "a" => 1 ]; \\}',
		'braces should be escaped'
	);
	assert.strictEqual(
		escapeMdxText('a <Tag> and \\ backslash'),
		'a \\<Tag> and \\\\ backslash',
		'JSX openers and backslashes should be escaped'
	);
	assert.strictEqual(escapeMdxText(null), '', 'nullish input should render empty');

	const index = renderRecipesIndex([
		{
			title: 'Recipe with { braces } in title',
			uri: '/recipes/braces',
			group: 'Misc',
			summary: "add_action( 'init', function() { do_stuff(); } );",
		},
	]);

	assert(
		index.includes('[Recipe with \\{ braces \\} in title](/recipes/braces)'),
		'index should escape MDX delimiters in titles'
	);
	assert(
		index.includes("add_action( 'init', function() \\{ do_stuff(); \\} );"),
		'index should escape MDX delimiters in summaries'
	);
	assert(!/[^\\]\{/.test(index), 'no unescaped opening braces should remain in the index');

	console.log('generate-recipe-docs.test.js: ok');
}

run();
