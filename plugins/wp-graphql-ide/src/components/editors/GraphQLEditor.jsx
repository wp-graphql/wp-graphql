import React, { useRef, useEffect, useCallback } from 'react';
import {
	EditorView,
	hoverTooltip,
	keymap,
	placeholder as cmPlaceholder,
	tooltips,
} from '@codemirror/view';
import { Compartment, EditorState } from '@codemirror/state';
import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { tags as t } from '@lezer/highlight';
import { graphql, jump, updateSchema, offsetToPos } from 'cm6-graphql';
import { getHoverInformation } from 'graphql-language-service';
import { basicSetup } from 'codemirror';

/**
 * GraphQL syntax-highlighting palette tuned for the WP admin light surface.
 *
 * Why this exists: cm6-graphql tags GraphQL field names as plain
 * `tags.propertyName`, but `@codemirror/language`'s defaultHighlightStyle
 * only has a rule for `tags.definition(tags.propertyName)` — so without an
 * explicit style, every field/argument/directive in a query renders in the
 * default body color. This style fills those gaps and replaces the muted
 * defaults with a palette that reads cleanly next to WordPress's UI chrome.
 */
const wpgraphqlHighlightStyle = HighlightStyle.define([
	// Keywords: query, mutation, fragment, on, etc.
	{ tag: t.keyword, color: '#a31515' },
	{ tag: t.definitionKeyword, color: '#a31515', fontWeight: '600' },

	// Field & argument names — the bulk of any query.
	{ tag: t.propertyName, color: '#1f61a0' },
	{ tag: t.attributeName, color: '#996800' },

	// Type references and atoms (Name nodes outside fields).
	{ tag: t.typeName, color: '#117a3e' },
	{ tag: t.atom, color: '#1f61a0' },
	{ tag: t.className, color: '#117a3e' },

	// Values.
	{ tag: t.string, color: '#a31515' },
	{ tag: t.number, color: '#005cc5' },
	{ tag: t.integer, color: '#005cc5' },
	{ tag: t.float, color: '#005cc5' },
	{ tag: t.bool, color: '#005cc5' },
	{ tag: t.null, color: '#005cc5' },
	{ tag: t.special(t.name), color: '#996800' }, // EnumValue

	// Variables ($foo).
	{ tag: t.variableName, color: '#aa6900' },

	// Directives (@include, @skip).
	{ tag: t.modifier, color: '#7a3e9d' },

	// Comments.
	{
		tag: [t.comment, t.lineComment, t.blockComment],
		color: '#6a9955',
		fontStyle: 'italic',
	},

	// Punctuation kept neutral so braces/commas don't distract.
	{ tag: t.punctuation, color: '#50575e' },
]);

const FIELD_NAME_RE = /[A-Za-z0-9_$]/;

// Decode HTML entities (`&gt;`, `&amp;`, `&#39;`, etc.) without executing any
// markup. Schema descriptions can contain entities when authored through
// admin UIs that escape on save (e.g. `WP_User-&gt;user_email`).
const decodeEntities = (str) => {
	if (!str || typeof str !== 'string' || str.indexOf('&') === -1) {
		return str || '';
	}
	const ta = document.createElement('textarea');
	ta.innerHTML = str;
	return ta.value;
};

const appendSpan = (parent, text, className) => {
	const span = document.createElement('span');
	if (className) {
		span.className = className;
	}
	span.textContent = text;
	parent.appendChild(span);
};

// Render a hover-signature line with the same color palette as the syntax
// highlighter. Three shapes are recognized:
//   1. `TypeName.fieldName: ReturnType`  — field on a type
//   2. `argName: ArgType`                 — argument or simple "name: type"
//   3. anything else                      — falls through as plain text
const renderSignatureInto = (parent, signature) => {
	const text = decodeEntities(signature);

	// Try field-on-type first so the regex doesn't match a bare `name:` line.
	const fieldMatch = text.match(/^([A-Z][\w]*)(\.)(\w+)(\s*:\s*)(.+)$/);
	if (fieldMatch) {
		appendSpan(parent, fieldMatch[1], 'wpgraphql-ide-hov-type');
		appendSpan(parent, fieldMatch[2], 'wpgraphql-ide-hov-punct');
		appendSpan(parent, fieldMatch[3], 'wpgraphql-ide-hov-field');
		appendSpan(parent, fieldMatch[4], 'wpgraphql-ide-hov-punct');
		appendSpan(parent, fieldMatch[5], 'wpgraphql-ide-hov-type');
		return;
	}

	const nameTypeMatch = text.match(/^(\w+)(\s*:\s*)(.+)$/);
	if (nameTypeMatch) {
		appendSpan(parent, nameTypeMatch[1], 'wpgraphql-ide-hov-arg');
		appendSpan(parent, nameTypeMatch[2], 'wpgraphql-ide-hov-punct');
		appendSpan(parent, nameTypeMatch[3], 'wpgraphql-ide-hov-type');
		return;
	}

	parent.textContent = text;
};

// Render a description block with HTML entities decoded and inline
// `backtick-wrapped` snippets styled as code.
const renderDescriptionInto = (parent, description) => {
	const text = decodeEntities(description);
	const parts = text.split(/(`[^`]+`)/g);
	for (const part of parts) {
		if (part.length > 1 && part.startsWith('`') && part.endsWith('`')) {
			appendSpan(parent, part.slice(1, -1), 'wpgraphql-ide-hov-code');
		} else if (part) {
			parent.appendChild(document.createTextNode(part));
		}
	}
};

/**
 * Build a CodeMirror hoverTooltip extension that surfaces GraphQL schema docs
 * (descriptions, return types, deprecation notes) for whatever field, type,
 * argument, or directive the cursor is over.
 *
 * cm6-graphql 0.2.x ships completion + lint but no hover provider, so we wire
 * one up using `getHoverInformation` from `graphql-language-service`. The
 * schema is read through a ref each call so the hook stays valid across
 * schema reloads without recreating the editor.
 *
 * @param {{ current: import('graphql').GraphQLSchema | null | undefined }} schemaRef
 *
 * @return {import('@codemirror/state').Extension}
 */
const buildHoverTooltip = (schemaRef) =>
	hoverTooltip((view, pos) => {
		const schema = schemaRef.current;
		if (!schema) {
			return null;
		}

		const queryText = view.state.doc.toString();
		if (!queryText.trim()) {
			return null;
		}

		let contents;
		try {
			const cursor = offsetToPos(view.state.doc, pos);
			contents = getHoverInformation(
				schema,
				queryText,
				cursor,
				undefined,
				{ useMarkdown: false }
			);
		} catch {
			return null;
		}

		if (!contents) {
			return null;
		}

		// `Hover['contents']` can be a string, MarkupContent ({ kind, value }),
		// or an array of either. Normalize to a single string.
		let text = '';
		if (typeof contents === 'string') {
			text = contents;
		} else if (Array.isArray(contents)) {
			text = contents
				.map((c) => (typeof c === 'string' ? c : c?.value || ''))
				.filter(Boolean)
				.join('\n\n');
		} else if (contents && typeof contents === 'object' && contents.value) {
			text = contents.value;
		}

		text = text.trim();
		if (!text) {
			return null;
		}

		// Anchor the tooltip on the identifier under the cursor so it tracks
		// the token rather than the precise pixel position.
		const line = view.state.doc.lineAt(pos);
		let from = pos;
		let to = pos;
		while (
			from > line.from &&
			FIELD_NAME_RE.test(line.text[from - line.from - 1])
		) {
			from -= 1;
		}
		while (to < line.to && FIELD_NAME_RE.test(line.text[to - line.from])) {
			to += 1;
		}
		if (from === to) {
			return null;
		}

		// `getHoverInformation` formats the result as a type signature line
		// followed (optionally) by a blank line and a prose description.
		// Split on the first blank line so we can style each section in a
		// way that matches the WP admin docs panel — monospaced signature,
		// regular-weight prose body, separated by a hairline divider.
		const splitIdx = text.search(/\n\s*\n/);
		const signature =
			splitIdx === -1 ? text : text.slice(0, splitIdx).trim();
		const description = splitIdx === -1 ? '' : text.slice(splitIdx).trim();

		return {
			pos: from,
			end: to,
			above: true,
			create() {
				const dom = document.createElement('div');
				dom.className = 'cm-tooltip-section wpgraphql-ide-hover';

				if (signature) {
					const sigEl = document.createElement('div');
					sigEl.className = 'wpgraphql-ide-hover-signature';
					renderSignatureInto(sigEl, signature);
					dom.appendChild(sigEl);
				}

				if (description) {
					const descEl = document.createElement('div');
					descEl.className = 'wpgraphql-ide-hover-description';
					renderDescriptionInto(descEl, description);
					dom.appendChild(descEl);
				}

				return { dom };
			},
		};
	});

/**
 * CodeMirror 6 GraphQL editor with schema-driven autocomplete, linting, and syntax highlighting.
 *
 * @param {Object}   props
 * @param {string}   props.value          - Current editor content.
 * @param {Function} props.onChange       - Called with new content string on edit.
 * @param {Object}   [props.schema]       - GraphQL schema for autocomplete/linting.
 * @param {boolean}  [props.readOnly]     - If true, editor is not editable.
 * @param {string}   [props.placeholder]  - Placeholder text when empty.
 * @param {Array}    [props.extraKeys]    - Additional keybindings ({ key, run } objects).
 * @param {string}   [props.className]    - Additional CSS class for the wrapper.
 * @param {Function} [props.onShowInDocs] - Called with `(field, type, parentType)` when
 *                                        the user cmd/ctrl-clicks an identifier so the
 *                                        parent can navigate the Docs panel.
 */
export function GraphQLEditor({
	value = '',
	onChange,
	schema,
	readOnly = false,
	placeholder = '',
	extraKeys = [],
	className = '',
	onShowInDocs,
}) {
	const containerRef = useRef(null);
	const viewRef = useRef(null);
	const onChangeRef = useRef(onChange);
	const readOnlyCompartment = useRef(new Compartment());
	const graphqlCompartment = useRef(new Compartment());
	const schemaRef = useRef(schema);
	const onShowInDocsRef = useRef(onShowInDocs);

	// Keep callback ref current without recreating the editor.
	useEffect(() => {
		onChangeRef.current = onChange;
	}, [onChange]);

	// Keep the schema reference current so the hover tooltip extension can
	// resolve fields against the latest schema without rebuilding the editor.
	useEffect(() => {
		schemaRef.current = schema;
	}, [schema]);

	// Same trick for the docs-jump callback so cmd-click stays wired even
	// when the parent re-renders with a fresh handler reference.
	useEffect(() => {
		onShowInDocsRef.current = onShowInDocs;
	}, [onShowInDocs]);

	// Initialize CodeMirror.
	useEffect(() => {
		if (!containerRef.current) {
			return;
		}

		// Single options object passed to graphql() so the cmd-click handler
		// is wired the same way on initial config and on every reconfigure.
		// We forward to the latest onShowInDocs via the ref so prop updates
		// from the parent don't require rebuilding the editor.
		const graphqlOpts = {
			onShowInDocs: (field, type, parentType) => {
				if (onShowInDocsRef.current) {
					onShowInDocsRef.current(field, type, parentType);
				}
			},
		};

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
							graphql(schema || undefined, graphqlOpts)
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
				hasContent ? graphql(schema || undefined, graphqlOpts) : []
			),
			// `jump` adds the cmd/ctrl-click "go to docs" gesture. It calls
			// the `onShowInDocs` callback registered via graphql() options.
			jump,
			// Apply the WP-admin GraphQL palette. basicSetup includes
			// defaultHighlightStyle as a fallback, so this non-fallback
			// registration takes precedence.
			syntaxHighlighting(wpgraphqlHighlightStyle),
			tooltips({ parent: document.body }),
			buildHoverTooltip(schemaRef),
			updateListener,
			// Keep contenteditable on even when readOnly so the user can
			// focus, select (Cmd+A), and copy. EditorState.readOnly blocks
			// the actual edits.
			readOnlyCompartment.current.of([
				EditorView.editable.of(true),
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
			className={`wpgraphql-ide-editor wpgraphql-ide-graphql-editor ${className}`.trim()}
		/>
	);
}
