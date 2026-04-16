import React, { useRef, useEffect, useCallback } from 'react';
import { EditorView } from '@codemirror/view';
import { EditorState } from '@codemirror/state';
import { json } from '@codemirror/lang-json';
import { basicSetup } from 'codemirror';

/**
 * Read-only CodeMirror 6 JSON viewer for GraphQL responses.
 *
 * @param {Object} props
 * @param {string} props.value       - JSON response string to display.
 * @param {string} [props.className] - Additional CSS class for the wrapper.
 */
export function ResponseViewer({ value = '', className = '' }) {
	const containerRef = useRef(null);
	const viewRef = useRef(null);

	useEffect(() => {
		if (!containerRef.current) {
			return;
		}

		const extensions = [
			basicSetup,
			json(),
			EditorView.editable.of(false),
			EditorState.readOnly.of(true),
		];

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

	// Sync response content.
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

	const setRef = useCallback((el) => {
		containerRef.current = el;
	}, []);

	return (
		<div
			ref={setRef}
			className={`wpgraphql-ide-editor wpgraphql-ide-response-viewer ${className}`.trim()}
		/>
	);
}
