import React, { useRef, useEffect, useCallback } from 'react';
import {
	EditorView,
	keymap,
	placeholder as cmPlaceholder,
} from '@codemirror/view';
import { Compartment, EditorState } from '@codemirror/state';
import { graphql, updateSchema } from 'cm6-graphql';
import { basicSetup } from 'codemirror';

/**
 * CodeMirror 6 GraphQL editor with schema-driven autocomplete, linting, and syntax highlighting.
 *
 * @param {Object}   props
 * @param {string}   props.value         - Current editor content.
 * @param {Function} props.onChange      - Called with new content string on edit.
 * @param {Object}   [props.schema]      - GraphQL schema for autocomplete/linting.
 * @param {boolean}  [props.readOnly]    - If true, editor is not editable.
 * @param {string}   [props.placeholder] - Placeholder text when empty.
 * @param {Array}    [props.extraKeys]   - Additional keybindings ({ key, run } objects).
 * @param {string}   [props.className]   - Additional CSS class for the wrapper.
 */
export function GraphQLEditor({
	value = '',
	onChange,
	schema,
	readOnly = false,
	placeholder = '',
	extraKeys = [],
	className = '',
}) {
	const containerRef = useRef(null);
	const viewRef = useRef(null);
	const onChangeRef = useRef(onChange);
	const readOnlyCompartment = useRef(new Compartment());
	const graphqlCompartment = useRef(new Compartment());

	// Keep callback ref current without recreating the editor.
	useEffect(() => {
		onChangeRef.current = onChange;
	}, [onChange]);

	// Initialize CodeMirror.
	useEffect(() => {
		if (!containerRef.current) {
			return;
		}

		const updateListener = EditorView.updateListener.of((update) => {
			if (update.docChanged) {
				if (onChangeRef.current) {
					onChangeRef.current(update.state.doc.toString());
				}
				// Activate graphql extension once the user types something.
				const doc = update.state.doc.toString();
				if (doc.trim().length > 0 && graphqlCompartment.current) {
					update.view.dispatch({
						effects: graphqlCompartment.current.reconfigure(
							graphql(schema || undefined)
						),
					});
				}
			}
		});

		// Defer graphql linting until the doc has content to avoid
		// a red lint dot on an empty editor.
		const hasContent = value && value.trim().length > 0;
		const extensions = [
			basicSetup,
			graphqlCompartment.current.of(
				hasContent ? graphql(schema || undefined) : []
			),
			updateListener,
			readOnlyCompartment.current.of([
				EditorView.editable.of(!readOnly),
				EditorState.readOnly.of(readOnly),
			]),
		];

		if (placeholder) {
			extensions.push(cmPlaceholder(placeholder));
		}

		if (extraKeys.length > 0) {
			extensions.push(keymap.of(extraKeys));
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
		// Only run on mount/unmount. Value synced separately below.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// Sync schema changes into the existing editor.
	useEffect(() => {
		if (viewRef.current && schema) {
			updateSchema(viewRef.current, schema);
		}
	}, [schema]);

	// Sync external value changes (e.g., prettify, load from history).
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
				EditorView.editable.of(!readOnly),
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
			className={`wpgraphql-ide-editor wpgraphql-ide-graphql-editor ${className}`.trim()}
		/>
	);
}
