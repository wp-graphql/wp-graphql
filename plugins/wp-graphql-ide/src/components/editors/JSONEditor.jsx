import React, { useRef, useEffect, useCallback } from 'react';
import { EditorView, placeholder as cmPlaceholder } from '@codemirror/view';
import { Compartment, EditorState } from '@codemirror/state';
import { json } from '@codemirror/lang-json';
import { basicSetup } from 'codemirror';

/**
 * CodeMirror 6 JSON editor for variables and headers.
 *
 * @param {Object}   props
 * @param {string}   props.value         - Current editor content.
 * @param {Function} props.onChange      - Called with new content string on edit.
 * @param {boolean}  [props.readOnly]    - If true, editor is not editable.
 * @param {string}   [props.placeholder] - Placeholder text when empty.
 * @param {boolean}  [props.isHidden]    - If true, editor is visually hidden (display:none).
 * @param {string}   [props.className]   - Additional CSS class for the wrapper.
 */
export function JSONEditor({
	value = '',
	onChange,
	readOnly = false,
	placeholder = '',
	isHidden = false,
	className = '',
}) {
	const containerRef = useRef(null);
	const viewRef = useRef(null);
	const onChangeRef = useRef(onChange);
	const readOnlyCompartment = useRef(new Compartment());

	useEffect(() => {
		onChangeRef.current = onChange;
	}, [onChange]);

	useEffect(() => {
		if (!containerRef.current) {
			return;
		}

		const updateListener = EditorView.updateListener.of((update) => {
			if (update.docChanged && onChangeRef.current) {
				onChangeRef.current(update.state.doc.toString());
			}
		});

		const extensions = [
			basicSetup,
			json(),
			updateListener,
			readOnlyCompartment.current.of([
				EditorView.editable.of(!readOnly),
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
			className={`wpgraphql-ide-editor wpgraphql-ide-json-editor ${className}`.trim()}
			style={isHidden ? { display: 'none' } : undefined}
		/>
	);
}
