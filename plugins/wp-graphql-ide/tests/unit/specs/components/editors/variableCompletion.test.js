import { CompletionContext } from '@codemirror/autocomplete';
import { EditorState } from '@codemirror/state';
import { EditorView } from '@codemirror/view';
import { json } from '@codemirror/lang-json';
import {
	GraphQLBoolean,
	GraphQLEnumType,
	GraphQLInputObjectType,
	GraphQLInt,
	GraphQLList,
	GraphQLNonNull,
	GraphQLString,
} from 'graphql';
import { createVariableCompletionSource } from '../../../../../src/components/editors/variableCompletion';

const Status = new GraphQLEnumType({
	name: 'Status',
	values: { DRAFT: {}, PUBLISH: {} },
});

// Spin up a real EditorState/View so the source can read the syntax
// tree exactly as it would in the browser. The view is detached — the
// CompletionContext only needs a `state`.
function makeContext(doc, cursorMarker = '|') {
	const idx = doc.indexOf(cursorMarker);
	if (idx === -1) {
		throw new Error('Test doc must contain a "|" cursor marker.');
	}
	const text = doc.slice(0, idx) + doc.slice(idx + 1);
	const state = EditorState.create({
		doc: text,
		extensions: [json()],
	});
	const view = new EditorView({
		state,
		parent: document.createElement('div'),
	});
	return new CompletionContext(view.state, idx, false);
}

function getSource(types) {
	const ref = { current: types };
	return createVariableCompletionSource(ref);
}

describe('createVariableCompletionSource', () => {
	it('returns null when no variables are declared', () => {
		const source = getSource(null);
		const ctx = makeContext('{ |}');
		expect(source(ctx)).toBeNull();
	});

	it('suggests variable names at the top-level property slot', () => {
		const source = getSource({ first: GraphQLInt, search: GraphQLString });
		const ctx = makeContext('{ |}');
		const result = source(ctx);
		expect(result).not.toBeNull();
		const labels = result.options.map((o) => o.label).sort();
		expect(labels).toEqual(['"first"', '"search"']);
		// `apply` lands the closing quote and colon so the user can
		// keep typing the value immediately.
		const first = result.options.find((o) => o.label === '"first"');
		expect(first.apply).toBe('"first": ');
		expect(first.detail).toBe('Int');
	});

	it('suggests variable names while the user is mid-key', () => {
		const source = getSource({ first: GraphQLInt, last: GraphQLInt });
		const ctx = makeContext('{ "fi|" }');
		const result = source(ctx);
		expect(result).not.toBeNull();
		const labels = result.options.map((o) => o.label);
		expect(labels).toContain('"first"');
	});

	it('filters out keys that are already present', () => {
		const source = getSource({ first: GraphQLInt, last: GraphQLInt });
		const ctx = makeContext('{"first": 1, |}');
		const result = source(ctx);
		expect(result.options.map((o) => o.label)).toEqual(['"last"']);
	});

	it('keeps the currently-edited key in the suggestion list', () => {
		// User is editing the existing key — we don't want to filter it
		// out as a "duplicate" of itself, otherwise the completion
		// dropdown disappears mid-typing.
		const source = getSource({ first: GraphQLInt, last: GraphQLInt });
		const ctx = makeContext('{"fi|rst": 1}');
		const result = source(ctx);
		const labels = result.options.map((o) => o.label);
		expect(labels).toContain('"first"');
	});

	it('returns no suggestions inside a value slot for unsuggestable types', () => {
		// Int is free-form — we don't try to guess values.
		const source = getSource({ first: GraphQLInt });
		const ctx = makeContext('{"first": |}');
		expect(source(ctx)).toBeNull();
	});

	it('suggests enum values at the matching value slot', () => {
		const source = getSource({ status: Status });
		const ctx = makeContext('{"status": |}');
		const result = source(ctx);
		expect(result).not.toBeNull();
		const labels = result.options.map((o) => o.label).sort();
		expect(labels).toEqual(['"DRAFT"', '"PUBLISH"', 'null']);
	});

	it('suggests true/false for Boolean variables', () => {
		const source = getSource({ active: GraphQLBoolean });
		const ctx = makeContext('{"active": |}');
		const result = source(ctx);
		expect(result.options.map((o) => o.label).sort()).toEqual([
			'false',
			'true',
		]);
	});

	it('drops the null suggestion for non-null enum types', () => {
		const source = getSource({
			status: new GraphQLNonNull(Status),
		});
		const ctx = makeContext('{"status": |}');
		const result = source(ctx);
		const labels = result.options.map((o) => o.label);
		expect(labels).toContain('"DRAFT"');
		expect(labels).not.toContain('null');
	});

	it('does not complete inside nested arrays', () => {
		// String list — no value-type suggestions for free-form strings.
		const source = getSource({ tags: new GraphQLList(GraphQLString) });
		const ctx = makeContext('{"tags": [|]}');
		expect(source(ctx)).toBeNull();
	});

	describe('nested input objects', () => {
		const NestedInput = new GraphQLInputObjectType({
			name: 'NestedInput',
			fields: () => ({ flag: { type: GraphQLBoolean } }),
		});
		const WhereInput = new GraphQLInputObjectType({
			name: 'WhereInput',
			fields: () => ({
				status: { type: Status },
				search: { type: GraphQLString },
				nested: { type: NestedInput },
			}),
		});

		it('suggests input-object fields at the nested key slot', () => {
			const source = getSource({ where: WhereInput });
			const ctx = makeContext('{"where": {|}}');
			const result = source(ctx);
			expect(result).not.toBeNull();
			const labels = result.options.map((o) => o.label).sort();
			expect(labels).toEqual(['"nested"', '"search"', '"status"']);
			const status = result.options.find((o) => o.label === '"status"');
			expect(status.detail).toBe('Status');
			expect(status.apply).toBe('"status": ');
		});

		it('suggests enum values at a nested value slot', () => {
			const source = getSource({ where: WhereInput });
			const ctx = makeContext('{"where": {"status": |}}');
			const result = source(ctx);
			expect(result).not.toBeNull();
			const labels = result.options.map((o) => o.label).sort();
			expect(labels).toEqual(['"DRAFT"', '"PUBLISH"', 'null']);
		});

		it('descends through multiple levels of nesting', () => {
			const source = getSource({ where: WhereInput });
			const ctx = makeContext('{"where": {"nested": {"flag": |}}}');
			const result = source(ctx);
			expect(result.options.map((o) => o.label).sort()).toEqual([
				'false',
				'true',
			]);
		});

		it('suggests input fields inside list-of-input-object', () => {
			const ListedInput = new GraphQLInputObjectType({
				name: 'ListedInput',
				fields: () => ({ name: { type: GraphQLString } }),
			});
			const source = getSource({
				items: new GraphQLList(ListedInput),
			});
			const ctx = makeContext('{"items": [{|}]}');
			const result = source(ctx);
			expect(result).not.toBeNull();
			expect(result.options.map((o) => o.label)).toEqual(['"name"']);
		});

		it('filters already-present nested keys', () => {
			const source = getSource({ where: WhereInput });
			const ctx = makeContext('{"where": {"status": "DRAFT", |}}');
			const result = source(ctx);
			expect(result.options.map((o) => o.label).sort()).toEqual([
				'"nested"',
				'"search"',
			]);
		});
	});
});
