import React, { useRef, useEffect, useCallback } from 'react';
import {
	EditorView,
	keymap,
	placeholder as cmPlaceholder,
} from '@codemirror/view';
import { Compartment, EditorState } from '@codemirror/state';
import { indentWithTab } from '@codemirror/commands';
import { json, jsonLanguage, jsonParseLinter } from '@codemirror/lang-json';
import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { linter } from '@codemirror/lint';
import { tags as t } from '@lezer/highlight';
import { basicSetup } from 'codemirror';
import { validateVariableTypes } from './variableLinter';
import { createVariableCompletionSource } from './variableCompletion';

// Explicit highlight palette for JSON. basicSetup includes
// `defaultHighlightStyle` as a fallback, but its colors are tuned for
// dark themes and read as nearly-grey on the WP admin light surface —
// users were getting an unstyled-looking editor for the Variables and
// Headers tabs. The colors here mirror the reference (classic GraphiQL)
// palette: green property names, red string values, blue numbers,
// purple booleans/null. Token names come from `@lezer/json`'s
// `jsonHighlighting`.
const jsonHighlightStyle = HighlightStyle.define([
	{ tag: t.propertyName, color: '#2e7d32' },
	{ tag: t.string, color: '#a31515' },
	{ tag: t.number, color: '#1751c1' },
	{ tag: t.bool, color: '#7b1fa2' },
	{ tag: t.null, color: '#7b1fa2' },
	{ tag: [t.brace, t.squareBracket, t.separator], color: '#586069' },
]);

/**
 * CodeMirror 6 JSON editor for variables and headers.
 *
 * @param {Object}   props
 * @param {string}   props.value            - Current editor content.
 * @param {Function} props.onChange         - Called with new content string on edit.
 * @param {boolean}  [props.readOnly]       - If true, editor is not editable.
 * @param {string}   [props.placeholder]    - Placeholder text when empty.
 * @param {boolean}  [props.isHidden]       - If true, editor is visually hidden (display:none).
 * @param {string}   [props.className]      - Additional CSS class for the wrapper.
 * @param {Object}   [props.variableToType] - GraphQL variable name → InputType map.
 *                                          When provided, the editor lints values
 *                                          against the operation's declared types.
 */
export function JSONEditor({
	value = '',
	onChange,
	readOnly = false,
	placeholder = '',
	isHidden = false,
	className = '',
	variableToType,
}) {
	const containerRef = useRef(null);
	const viewRef = useRef(null);
	const onChangeRef = useRef(onChange);
	const readOnlyCompartment = useRef(new Compartment());
	const variableLintCompartment = useRef(new Compartment());
	const variableToTypeRef = useRef(variableToType);

	useEffect(() => {
		onChangeRef.current = onChange;
	}, [onChange]);

	// Keep the variable-type ref current so the linter callback always
	// reads the latest types without rebuilding the editor or
	// reconfiguring on every operation tweak (the linter re-runs
	// automatically on doc changes).
	useEffect(() => {
		variableToTypeRef.current = variableToType;
	}, [variableToType]);

	useEffect(() => {
		if (!containerRef.current) {
			return;
		}

		const updateListener = EditorView.updateListener.of((update) => {
			if (update.docChanged && onChangeRef.current) {
				onChangeRef.current(update.state.doc.toString());
			}
		});

		// JSON syntax linter (always on) gives squiggles + gutter dots
		// for malformed JSON. The variable linter is gated behind a
		// compartment so the Headers tab — which has no schema — pays
		// nothing for type validation it can't do.
		const jsonSyntaxLinter = linter(jsonParseLinter());
		const variableTypeLinter = linter((view) =>
			validateVariableTypes(view, variableToTypeRef.current)
		);

		// Variable-name autocomplete source (e.g., typing `fi` inside
		// the variables object suggests `"first": `). Scoped to the
		// JSON language so it doesn't bleed into other editors.
		// `basicSetup` already enables `autocompletion()`; this just
		// contributes a source through the language-data channel.
		const variableCompletions = jsonLanguage.data.of({
			autocomplete: createVariableCompletionSource(variableToTypeRef),
		});

		const extensions = [
			basicSetup,
			// Match the GraphQL editor: Tab indents instead of moving
			// focus out. Esc-then-Tab still escapes for keyboard users.
			keymap.of([indentWithTab]),
			json(),
			variableCompletions,
			syntaxHighlighting(jsonHighlightStyle),
			jsonSyntaxLinter,
			variableLintCompartment.current.of(
				variableToType ? variableTypeLinter : []
			),
			updateListener,
			readOnlyCompartment.current.of([
				EditorView.editable.of(true),
				EditorState.readOnly.of(readOnly),
			]),
		];

		if (placeholder) {
			extensions.push(cmPlaceholder(placeholder));
		}

		const state = EditorState.create({
			doc: value,
			extensions,
		});

		const view = new EditorView({
			state,
			parent: containerRef.current,
		});

		viewRef.current = view;

		return () => {
			view.destroy();
			viewRef.current = null;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// Toggle the variable linter when `variableToType` switches between
	// "schema available" and "no schema" — flipping a Compartment is
	// cheaper than rebuilding the editor and avoids losing cursor state.
	useEffect(() => {
		const view = viewRef.current;
		if (!view) {
			return;
		}
		const variableTypeLinter = linter((v) =>
			validateVariableTypes(v, variableToTypeRef.current)
		);
		view.dispatch({
			effects: variableLintCompartment.current.reconfigure(
				variableToType ? variableTypeLinter : []
			),
		});
		// We only care whether the linter is present, not the specific
		// types — those flow through the ref. Comparing object identity
		// via `Boolean(variableToType)` keeps reconfigures rare.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [Boolean(variableToType)]);

	// Sync external value changes.
	useEffect(() => {
		const view = viewRef.current;
		if (!view) {
			return;
		}
		const currentDoc = view.state.doc.toString();
		if (value !== currentDoc) {
			view.dispatch({
				changes: {
					from: 0,
					to: currentDoc.length,
					insert: value,
				},
			});
		}
	}, [value]);

	// Sync readOnly changes.
	useEffect(() => {
		const view = viewRef.current;
		if (!view) {
			return;
		}
		view.dispatch({
			effects: readOnlyCompartment.current.reconfigure([
				EditorView.editable.of(true),
				EditorState.readOnly.of(readOnly),
			]),
		});
	}, [readOnly]);

	const setRef = useCallback((el) => {
		containerRef.current = el;
	}, []);

	return (
		<div
			ref={setRef}
			className={`wpgraphql-ide-editor wpgraphql-ide-json-editor ${className}`.trim()}
			style={isHidden ? { display: 'none' } : undefined}
		/>
	);
}
