/**
 * Inline run-operation widgets.
 *
 * For each top-level operation in the GraphQL document the editor
 * paints a tiny play button on the line where that operation
 * declares itself (`query Foo { … }` / `mutation Bar { … }` etc.).
 * Clicking it runs *that* operation directly, eliminating the
 * floating pill's "click play → pick operation" two-step on multi-
 * operation documents.
 *
 * Implementation:
 *
 *   - A StateField holds the current decoration set.
 *   - A StateEffect updates the field when operations / callback
 *     change. The owning editor dispatches the effect from a
 *     `useEffect` so the widgets track parsed-AST updates without
 *     re-creating the entire CM6 view.
 *   - Each operation gets a `Decoration.widget` placed at its
 *     start position with `side: -1` so the button renders inline
 *     *before* the `query` keyword. CSS handles spacing.
 */

import { Decoration, EditorView, WidgetType } from '@codemirror/view';
import { StateEffect, StateField } from '@codemirror/state';

const PLAY_GLYPH =
	'<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';

class RunOperationWidget extends WidgetType {
	constructor(name, onRun) {
		super();
		this.name = name;
		this.onRun = onRun;
	}

	// Decoration set rebuilds wholesale when operations change, but
	// equality lets CM6 reuse the same DOM node when the name (and
	// thus what running it does) hasn't moved.
	eq(other) {
		return other.name === this.name;
	}

	toDOM() {
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'wpgraphql-ide-inline-run-btn';
		btn.title = `Run ${this.name}`;
		btn.setAttribute('aria-label', `Run ${this.name}`);
		btn.innerHTML = PLAY_GLYPH;
		btn.addEventListener('mousedown', (event) => {
			// CM6 captures mousedown on widgets to manage selection;
			// preventing the default keeps the click from also moving
			// the caret into the operation header.
			event.preventDefault();
		});
		btn.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			if (typeof this.onRun === 'function') {
				this.onRun(this.name);
			}
		});
		return btn;
	}

	// We handle our own events — let them through rather than letting
	// CM6 swallow them as selection updates.
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
		// Map existing decorations through the change set so they
		// follow edits until the next setRunOperationsEffect arrives.
		// (Operation positions get recomputed from the parsed AST in
		// the React layer; this just keeps things stable between
		// re-parses.)
		let next = deco.map(tr.changes);
		for (const effect of tr.effects) {
			if (effect.is(setRunOperationsEffect)) {
				const { operations, onRun } = effect.value;
				const ranges = [];
				for (const op of operations) {
					if (
						typeof op?.from === 'number' &&
						typeof op?.name === 'string'
					) {
						ranges.push(
							Decoration.widget({
								widget: new RunOperationWidget(op.name, onRun),
								side: -1,
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
