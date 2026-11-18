#!/usr/bin/env node
/**
 * Tests for check-php-conventions.js.
 *
 * Usage: node scripts/check-php-conventions.test.js
 */

const assert = require('assert');
const {
	parseArgs,
	getDefaultBaseRef,
	isCheckedPath,
	blankStringsAndComments,
	extractDeclarations,
	findMissingSince,
	findUntranslatedStrings,
	checkFile,
	parseAddedLines,
	parseNameStatus,
	escapeWorkflowCommand,
} = require('./check-php-conventions');

let passed = 0;
function test(name, fn) {
	try {
		fn();
		passed++;
		console.log(`✓ ${name}`);
	} catch (error) {
		console.error(`✗ ${name}`);
		console.error(error);
		process.exit(1);
	}
}

const php = (body) => `<?php\n${body}\n`;

test('parseArgs splits on the first = only', () => {
	assert.deepStrictEqual(parseArgs(['--base-ref=a=b', '--all', 'ignored']), {
		'base-ref': 'a=b',
		all: true,
	});
});

test('getDefaultBaseRef uses the PR base, then the push before sha, then origin/main', () => {
	assert.strictEqual(
		getDefaultBaseRef({
			GITHUB_EVENT_NAME: 'pull_request',
			GITHUB_BASE_REF: 'main',
		}),
		'origin/main'
	);
	assert.strictEqual(
		getDefaultBaseRef({
			GITHUB_EVENT_NAME: 'pull_request',
			GITHUB_BASE_REF: 'release/x',
		}),
		'origin/release/x'
	);
	assert.strictEqual(
		getDefaultBaseRef({
			GITHUB_EVENT_NAME: 'push',
			EVENT_BEFORE: 'abc123',
		}),
		'abc123'
	);
	assert.strictEqual(
		getDefaultBaseRef({
			GITHUB_EVENT_NAME: 'push',
			EVENT_BEFORE: '0000000',
		}),
		'origin/main'
	);
	assert.strictEqual(getDefaultBaseRef({}), 'origin/main');
});

test('isCheckedPath covers plugin src and access-functions.php only', () => {
	assert.ok(
		isCheckedPath('plugins/wp-graphql/src/Registry/TypeRegistry.php')
	);
	assert.ok(isCheckedPath('plugins/wp-graphql-acf/access-functions.php'));
	assert.ok(!isCheckedPath('plugins/wp-graphql/tests/wpunit/FooTest.php'));
	assert.ok(!isCheckedPath('plugins/wp-graphql/src/README.md'));
	assert.ok(!isCheckedPath('plugins/wp-graphql/vendor/src/Foo.php'));
	assert.ok(!isCheckedPath('scripts/foo.php'));
});

test('blankStringsAndComments keeps docblocks and line breaks, blanks strings and other comments', () => {
	const source =
		"/** @since 1 */\n$a = 'function x'; // function y\n/* const Z */";
	const blanked = blankStringsAndComments(source);
	assert.strictEqual(blanked.split('\n').length, 3);
	assert.ok(blanked.includes('/** @since 1 */'));
	assert.ok(!blanked.includes('function x'));
	assert.ok(!blanked.includes('function y'));
	assert.ok(!blanked.includes('const Z'));
});

test('extractDeclarations finds non-private declarations and skips private ones and text', () => {
	const source = php(`
class Foo {
	const A = 1;
	private const B = 2;
	public static function bar() {}
	protected function baz() {}
	private function qux() {}
	function implicit() {}
	public function text() { return 'public function fake()'; }
}
interface Bar {}
trait Baz {}
function top_level() {}
`);
	const keys = extractDeclarations(source).map(({ key }) => key);
	assert.deepStrictEqual(keys, [
		'class:Foo',
		'const:A',
		'function:bar',
		'function:baz',
		'function:implicit',
		'function:text',
		'interface:Bar',
		'trait:Baz',
		'function:top_level',
	]);
});

test('findMissingSince flags a new declaration without @since', () => {
	const base = php('class Foo {\n}');
	const head = php('class Foo {\n\tpublic function bar() {}\n}');
	const problems = findMissingSince(base, head);
	assert.strictEqual(problems.length, 1);
	assert.strictEqual(problems[0].line, 3);
	assert.match(problems[0].message, /function "bar"/);
});

test('findMissingSince accepts @since, @inheritDoc, and attributes between docblock and declaration', () => {
	const base = php('class Foo {\n}');
	const head = php(`class Foo {
	/**
	 * Bar.
	 *
	 * @since x-release-please-version
	 */
	public function bar() {}

	/**
	 * {@inheritDoc}
	 */
	public function baz() {}

	/**
	 * Qux.
	 *
	 * @since x-release-please-version
	 */
	#[\\ReturnTypeWillChange]
	public function qux() {}
}`);
	assert.deepStrictEqual(findMissingSince(base, head), []);
});

test('findMissingSince ignores existing declarations, private ones, and a docblock without @since', () => {
	const base = php('class Foo {\n\tpublic function old() {}\n}');
	const head = php(`class Foo {
	public function old() {}
	private function hidden() {}
	/**
	 * No tag.
	 */
	public function documented_without_since() {}
}`);
	const problems = findMissingSince(base, head);
	assert.strictEqual(problems.length, 1);
	assert.match(problems[0].message, /documented_without_since/);
});

test('findMissingSince treats a second declaration with an existing name as new', () => {
	const base = php('class A {\n\tpublic function register() {}\n}');
	const head = php(
		'class A {\n\tpublic function register() {}\n}\nclass B {\n\tpublic function register() {}\n}'
	);
	const names = findMissingSince(base, head).map(({ message }) => message);
	assert.strictEqual(names.length, 2);
	assert.match(names[0], /class "B"/);
	assert.match(names[1], /function "register"/);
});

test("findMissingSince doesn't take a plain block comment for a docblock", () => {
	const head = php('/* @since 1.0 */\nfunction foo() {}');
	assert.strictEqual(findMissingSince('', head).length, 1);
});

test('findUntranslatedStrings flags literal user-facing values and options labels', () => {
	const head = php(`register_graphql_settings_field( 'section', [
	'name'    => 'mode',
	'label'   => 'Mode',
	'desc'    => __( 'Translated', 'domain' ),
	'options' => [
		'a' => 'Option A',
		'b' => __( 'Option B', 'domain' ),
		Document::C => 'Option C',
	],
] );`);
	const lines = findUntranslatedStrings(head, null).map(({ line }) => line);
	assert.deepStrictEqual(lines, [4, 7, 9]);
});

test('findUntranslatedStrings handles array() options and nested arrays', () => {
	const head = php(`$field = array(
	'options' => array(
		'a' => array( 'x' => 1 ),
		'b' => 'Option B',
	),
	'other' => 'not an option',
);`);
	const lines = findUntranslatedStrings(head, null).map(({ line }) => line);
	assert.deepStrictEqual(lines, [5]);
});

test('findUntranslatedStrings skips i18n-ignore, comments, keys inside strings, and non-letter values', () => {
	const head = php(`$a = [
	'label' => 'Deliberate', // i18n-ignore
	// 'label' => 'In a comment',
	'name'  => "'label' => 'in a string'",
	'title' => '%s',
	'desc'  => $variable,
];`);
	assert.deepStrictEqual(findUntranslatedStrings(head, null), []);
});

test('findUntranslatedStrings ignores an arrow inside a string in an options array', () => {
	const head = php(`$a = [
	'options' => [
		'a' => __( "Use => 'quotes'", 'domain' ),
	],
];`);
	assert.deepStrictEqual(findUntranslatedStrings(head, null), []);
});

test('findUntranslatedStrings only checks the given lines', () => {
	const head = php("$a = [\n\t'label' => 'One',\n\t'label' => 'Two',\n];");
	assert.deepStrictEqual(
		findUntranslatedStrings(head, new Set([4])).map(({ line }) => line),
		[4]
	);
});

test('parseAddedLines reads the new-file side of each hunk', () => {
	const diff = [
		'diff --git a/f.php b/f.php',
		'@@ -1,0 +2,3 @@',
		'+a',
		'+b',
		'+c',
		'@@ -10 +12 @@',
		'-old',
		'+new',
		'@@ -20,2 +23,0 @@',
		'-gone',
		'-gone',
	].join('\n');
	assert.deepStrictEqual([...parseAddedLines(diff)], [2, 3, 4, 12]);
});

test('parseNameStatus reads -z output and keeps added, modified, renamed and copied checked files', () => {
	const output =
		[
			'A',
			'plugins/wp-graphql/src/New.php',
			'M',
			'plugins/wp-graphql/src/Changed "quoted".php',
			'R087',
			'plugins/wp-graphql/src/Old.php',
			'plugins/wp-graphql/src/Moved.php',
			'C090',
			'plugins/wp-graphql/src/Source.php',
			'plugins/wp-graphql/src/Copy.php',
			'D',
			'plugins/wp-graphql/src/Deleted.php',
			'M',
			'plugins/wp-graphql/tests/wpunit/FooTest.php',
			'M',
			'README.md',
		].join('\0') + '\0';
	assert.deepStrictEqual(parseNameStatus(output), [
		{ path: 'plugins/wp-graphql/src/New.php', basePath: null },
		{
			path: 'plugins/wp-graphql/src/Changed "quoted".php',
			basePath: 'plugins/wp-graphql/src/Changed "quoted".php',
		},
		{
			path: 'plugins/wp-graphql/src/Moved.php',
			basePath: 'plugins/wp-graphql/src/Old.php',
		},
		{ path: 'plugins/wp-graphql/src/Copy.php', basePath: null },
	]);
	assert.deepStrictEqual(parseNameStatus(''), []);
});

test('escapeWorkflowCommand escapes properties and messages', () => {
	assert.strictEqual(
		escapeWorkflowCommand('a,b:c%d\ne', true),
		'a%2Cb%3Ac%25d%0Ae'
	);
	assert.strictEqual(
		escapeWorkflowCommand('Wrap it: a, b\n'),
		'Wrap it: a, b%0A'
	);
});

test('blankStringsAndComments blanks inline HTML until PHP reopens', () => {
	const source =
		"<?php\nif ( $x ) : ?>\n<p>Don't stop</p>\n<?php endif;\nfunction after() {}";
	const blanked = blankStringsAndComments(source);
	assert.ok(!blanked.includes("Don't"));
	assert.deepStrictEqual(
		extractDeclarations(source).map(({ key }) => key),
		['function:after']
	);
});

test('checkFile only reports problems on lines the change added', () => {
	const base = php("class Foo {\n\tconst OLD = 'x';\n}");
	const head = php(
		"class Foo {\n\tconst OLD = 'x';\n\tconst NEW_ONE = 'y';\n\t'label' => 'Old label',\n}"
	);
	const problems = checkFile({
		path: 'plugins/p/src/Foo.php',
		baseSource: base,
		headSource: head,
		addedLines: new Set([4]),
	});
	assert.deepStrictEqual(problems, [
		{
			path: 'plugins/p/src/Foo.php',
			line: 4,
			message:
				'New const "NEW_ONE" needs a docblock with @since x-release-please-version.',
		},
	]);
});

test('checkFile with every line checks strings only', () => {
	const head = php("function foo() {}\n$a = [ 'label' => 'Label' ];");
	const problems = checkFile({
		path: 'plugins/p/src/Foo.php',
		baseSource: '',
		headSource: head,
		addedLines: null,
	});
	assert.strictEqual(problems.length, 1);
	assert.strictEqual(problems[0].line, 3);
});

console.log(`\n${passed} tests passed.`);
