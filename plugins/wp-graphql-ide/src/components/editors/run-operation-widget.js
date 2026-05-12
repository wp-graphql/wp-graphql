/**
 * Inline per-operation execution pill widgets. A StateField holds the
 * decoration set; the owning editor dispatches setRunOperationsEffect
 * from a `useEffect` so widgets track parsed-AST updates without
 * recreating the CM6 view. IDELayout filters to empty for single-op
 * docs so the floating pill isn't duplicated.
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Decoration, EditorView, WidgetType } from '@codemirror/view';
import { StateEffect, StateField } from '@codemirror/state';
import { InlineExecutionPill } from './InlineExecutionPill';

class InlineExecutionPillWidget extends WidgetType {
	constructor(name, ctx) {
		super();
		this.name = name;
		this.ctx = ctx;
	}

	// Identity-compare ctx so callers must `useMemo` it; otherwise every
	// re-render tears down and remounts the React tree.
	eq(other) {
		return other.name === this.name && other.ctx === this.ctx;
	}

	toDOM() {
		const host = document.createElement('span');
		host.className = 'wpgraphql-ide-inline-pill-host';
		host.setAttribute('contenteditable', 'false');

		const root = createRoot(host);
		root.render(
			<InlineExecutionPill
				operationName={this.name}
				onRun={(opName) => {
					if (typeof this.ctx?.onRun === 'function') {
						this.ctx.onRun(opName);
					}
				}}
				avatarUrl={this.ctx?.avatarUrl}
				signInUrl={this.ctx?.signInUrl}
				showAuthControl={this.ctx?.showAuthControl !== false}
				isSchemaLoading={!!this.ctx?.isSchemaLoading}
			/>
		);
		host.__wpgraphqlIdeRoot = root;
		return host;
	}

	destroy(dom) {
		const root = dom?.__wpgraphqlIdeRoot;
		if (root) {
			// Defer unmount so React isn't asked to unmount mid-render
			// from inside CM6's update transaction.
			Promise.resolve().then(() => root.unmount());
			delete dom.__wpgraphqlIdeRoot;
		}
	}

	// React owns the subtree — let click/focus events through.
	ignoreEvent() {
		return false;
	}
}

export const setRunOperationsEffect = StateEffect.define();

export const runOperationsField = StateField.define({
	create() {
		return Decoration.none;
	},
	update(deco, tr) {
		// Map through edits so widgets stay anchored between re-parses;
		// authoritative positions arrive via setRunOperationsEffect.
		let next = deco.map(tr.changes);
		for (const effect of tr.effects) {
			if (effect.is(setRunOperationsEffect)) {
				const { operations, ctx } = effect.value;
				const ranges = [];
				for (const op of operations) {
					if (
						typeof op?.from === 'number' &&
						typeof op?.name === 'string'
					) {
						ranges.push(
							Decoration.widget({
								widget: new InlineExecutionPillWidget(
									op.name,
									ctx
								),
								side: 1,
							}).range(op.from)
						);
					}
				}
				next = Decoration.set(ranges, true);
			}
		}
		return next;
	},
	provide: (field) => EditorView.decorations.from(field),
});
