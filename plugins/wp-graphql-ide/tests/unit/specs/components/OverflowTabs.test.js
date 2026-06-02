/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

// `OverflowTabs` measures tab widths with `getBoundingClientRect` and a
// ResizeObserver in jsdom — neither runs in tests. Stub both, and give
// the container plenty of horizontal room so every tab stays in the
// visible range (no `+N` overflow dropdown to worry about).
window.ResizeObserver =
	window.ResizeObserver ||
	class {
		observe() {}
		unobserve() {}
		disconnect() {}
	};

Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
	configurable: true,
	get() {
		return 2000;
	},
});
Object.defineProperty(HTMLElement.prototype, 'offsetWidth', {
	configurable: true,
	get() {
		return 80;
	},
});

// eslint-disable-next-line import/first
import { OverflowTabs } from '../../../../src/components/OverflowTabs';

const TABS = [
	{ name: 'errors', title: 'Errors' },
	{ name: 'tracing', title: 'Tracing' },
	{ name: 'debug', title: 'Debug' },
];

function noopReorder(overrides = {}) {
	return {
		dragOverTab: null,
		onDragStart: () => () => {},
		onDragOver: () => () => {},
		onDragLeave: () => {},
		onDrop: () => () => {},
		onDragEnd: () => {},
		...overrides,
	};
}

describe('OverflowTabs reorder wiring', () => {
	it('renders tabs as non-draggable when no reorder bundle is provided', () => {
		render(
			<OverflowTabs tabs={TABS} initialTabName="errors">
				{() => <div>content</div>}
			</OverflowTabs>
		);
		const buttons = screen
			.getAllByRole('tab')
			.filter((b) => b.textContent !== '');
		expect(buttons.length).toBeGreaterThan(0);
		buttons.forEach((b) => {
			expect(b).not.toHaveAttribute('draggable', 'true');
		});
	});

	it('marks every tab draggable when the reorder bundle is provided', () => {
		render(
			<OverflowTabs
				tabs={TABS}
				initialTabName="errors"
				reorder={noopReorder()}
			>
				{() => <div>content</div>}
			</OverflowTabs>
		);
		const buttons = screen.getAllByRole('tab');
		expect(buttons.length).toBe(TABS.length);
		buttons.forEach((b) => {
			expect(b).toHaveAttribute('draggable', 'true');
		});
	});

	it('exposes each tab name via `data-tab-name` so CSS can target by identity instead of position', () => {
		// Users can drag-reorder the response strip, so a `:nth-child(N)`
		// selector would highlight the wrong tab once that ordering
		// changes. The Errors tab's red error indicator depends on this
		// attribute — losing it would silently regress that affordance.
		render(
			<OverflowTabs tabs={TABS} initialTabName="errors">
				{() => <div>content</div>}
			</OverflowTabs>
		);
		const buttons = screen.getAllByRole('tab');
		const names = buttons.map((b) => b.getAttribute('data-tab-name'));
		expect(names).toEqual(expect.arrayContaining(TABS.map((t) => t.name)));
	});

	it('applies the is-drag-* class on the hovered tab from dragOverTab', () => {
		render(
			<OverflowTabs
				tabs={TABS}
				initialTabName="errors"
				reorder={noopReorder({
					dragOverTab: { name: 'tracing', pos: 'left' },
				})}
			>
				{() => <div>content</div>}
			</OverflowTabs>
		);
		const tracing = screen.getByRole('tab', { name: 'Tracing' });
		expect(tracing).toHaveClass('is-drag-left');
	});

	it('marks the source tab is-dragging from onDragStart until onDragEnd', () => {
		render(
			<OverflowTabs
				tabs={TABS}
				initialTabName="errors"
				reorder={noopReorder()}
			>
				{() => <div>content</div>}
			</OverflowTabs>
		);
		const tracing = screen.getByRole('tab', { name: 'Tracing' });
		fireEvent.dragStart(tracing);
		expect(tracing).toHaveClass('is-dragging');
		fireEvent.dragEnd(tracing);
		expect(tracing).not.toHaveClass('is-dragging');
	});
});
