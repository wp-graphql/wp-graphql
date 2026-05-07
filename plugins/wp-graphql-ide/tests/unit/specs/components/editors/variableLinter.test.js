import { EditorState } from '@codemirror/state';
import { EditorView } from '@codemirror/view';
import { json } from '@codemirror/lang-json';
import {
	GraphQLEnumType,
	GraphQLInputObjectType,
	GraphQLInt,
	GraphQLList,
	GraphQLNonNull,
	GraphQLString,
} from 'graphql';
import { validateVariableTypes } from '../../../../../src/components/editors/variableLinter';

// Build a minimal EditorView attached to a detached DOM node so the
// CodeMirror state is real (syntax tree, document slicing) without
// needing a visible editor.
function makeView(doc) {
	const state = EditorState.create({
		doc,
		extensions: [json()],
	});
	return new EditorView({ state, parent: document.createElement('div') });
}

const Status = new GraphQLEnumType({
	name: 'Status',
	values: { DRAFT: {}, PUBLISH: {} },
});

const PostInput = new GraphQLInputObjectType({
	name: 'PostInput',
	fields: () => ({
		title: { type: new GraphQLNonNull(GraphQLString) },
		tagIds: { type: new GraphQLList(GraphQLInt) },
	}),
});

describe('validateVariableTypes', () => {
	it('returns no diagnostics when no variables are declared', () => {
		const view = makeView('{"first": 5}');
		expect(validateVariableTypes(view, null)).toEqual([]);
		expect(validateVariableTypes(view, {})).toEqual([]);
	});

	it('skips when JSON is unparseable so the syntax linter can lead', () => {
		const view = makeView('{"first": invalid}');
		const diagnostics = validateVariableTypes(view, {
			first: GraphQLInt,
		});
		expect(diagnostics).toEqual([]);
	});

	it('flags non-object roots', () => {
		const view = makeView('[1, 2, 3]');
		const diagnostics = validateVariableTypes(view, {
			first: GraphQLInt,
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].message).toMatch(/JSON object/);
	});

	it('produces no diagnostics when types match', () => {
		const view = makeView(
			'{"first": 5, "title": "Hello", "status": "DRAFT"}'
		);
		const diagnostics = validateVariableTypes(view, {
			first: GraphQLInt,
			title: GraphQLString,
			status: Status,
		});
		expect(diagnostics).toEqual([]);
	});

	it('flags scalar mismatches with positions on the offending value', () => {
		const doc = '{"first": "5"}';
		const view = makeView(doc);
		const diagnostics = validateVariableTypes(view, {
			first: GraphQLInt,
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].severity).toBe('error');
		expect(diagnostics[0].message).toMatch(/value of type "Int"/);
		// Underline only the value `"5"`, not the surrounding object.
		expect(doc.slice(diagnostics[0].from, diagnostics[0].to)).toBe('"5"');
	});

	it('flags missing required (non-null) variables', () => {
		const view = makeView('{}');
		const diagnostics = validateVariableTypes(view, {
			id: new GraphQLNonNull(GraphQLString),
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].severity).toBe('error');
		expect(diagnostics[0].message).toMatch(
			/\$id.*required type "String!".*not provided/
		);
	});

	it('does not flag missing nullable variables', () => {
		const view = makeView('{}');
		const diagnostics = validateVariableTypes(view, {
			search: GraphQLString,
		});
		expect(diagnostics).toEqual([]);
	});

	it('flags null assigned to a non-null variable', () => {
		const view = makeView('{"id": null}');
		const diagnostics = validateVariableTypes(view, {
			id: new GraphQLNonNull(GraphQLString),
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].message).toMatch(/non-null type "String!"/);
	});

	it('warns on unknown variable keys', () => {
		const view = makeView('{"bogus": 1}');
		const diagnostics = validateVariableTypes(view, {
			first: GraphQLInt,
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].severity).toBe('warning');
		expect(diagnostics[0].message).toMatch(/\$bogus/);
	});

	it('descends into list element types', () => {
		const doc = '{"ids": [1, "2", 3]}';
		const view = makeView(doc);
		const diagnostics = validateVariableTypes(view, {
			ids: new GraphQLList(GraphQLInt),
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].message).toMatch(/value of type "Int"/);
		expect(doc.slice(diagnostics[0].from, diagnostics[0].to)).toBe('"2"');
	});

	it('coerces a single scalar into a one-element list per the spec', () => {
		const view = makeView('{"ids": 1}');
		const diagnostics = validateVariableTypes(view, {
			ids: new GraphQLList(GraphQLInt),
		});
		expect(diagnostics).toEqual([]);
	});

	it('descends into input object fields', () => {
		const view = makeView('{"input": {"title": 42}}');
		const diagnostics = validateVariableTypes(view, {
			input: PostInput,
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].message).toMatch(/value of type "String"/);
	});

	it('flags missing required fields inside input objects', () => {
		const view = makeView('{"input": {}}');
		const diagnostics = validateVariableTypes(view, {
			input: PostInput,
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].message).toMatch(
			/title.*required type "String!".*not provided/
		);
	});

	it('warns on unknown fields inside input objects', () => {
		const view = makeView('{"input": {"title": "ok", "bogus": 1}}');
		const diagnostics = validateVariableTypes(view, {
			input: PostInput,
		});
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].severity).toBe('warning');
		expect(diagnostics[0].message).toMatch(/bogus/);
	});

	it('errors when an enum receives a non-string', () => {
		const view = makeView('{"status": 1}');
		const diagnostics = validateVariableTypes(view, { status: Status });
		expect(diagnostics).toHaveLength(1);
		expect(diagnostics[0].message).toMatch(/value of type "Status"/);
	});
});
