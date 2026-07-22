/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent, within } from '@testing-library/react';
import { buildSchema } from 'graphql';

// `@wordpress/components` probes matchMedia at render time; jsdom doesn't
// ship it. Stub before the component imports.
if (typeof window.matchMedia !== 'function') {
	window.matchMedia = () => ({
		matches: false,
		media: '',
		onchange: null,
		addListener: () => {},
		removeListener: () => {},
		addEventListener: () => {},
		removeEventListener: () => {},
		dispatchEvent: () => false,
	});
}

// The real `@wordpress/components` drags an untransformed ESM `uuid`
// into jest; the panel only uses `Button`, so stub it with a plain
// button element.
jest.mock('@wordpress/components', () => ({
	// eslint-disable-next-line react/prop-types
	Button: ({ children, onClick, className }) => (
		<button type="button" className={className} onClick={onClick}>
			{children}
		</button>
	),
}));

// Mock the wp-data app store the panel reads its schema from. Stash on
// global so jest.mock's factory can reach the same refs without tripping
// the "no out-of-scope variables" guard.
global.__dep = {
	schema: null,
	docsNavTarget: null,
	setDocsNavTarget: jest.fn(),
};

jest.mock('@wordpress/data', () => {
	const stores = {
		'wpgraphql-ide/app': {
			schema: () => global.__dep.schema,
			getDocsNavTarget: () => global.__dep.docsNavTarget,
		},
	};
	return {
		useSelect: (cb) => cb((name) => stores[name]),
		useDispatch: () => ({
			setDocsNavTarget: global.__dep.setDocsNavTarget,
		}),
	};
});

const { __dep } = global;

// eslint-disable-next-line import/first
import { DocsExplorerPanel } from '../../../../src/components/DocsExplorerPanel';

// Mirrors the shape from issue #4028: an interface hierarchy (interfaces
// implementing interfaces) plus object types implementing them, and a
// union to confirm existing sections are untouched.
const SDL = /* GraphQL */ `
	interface Node {
		id: ID!
	}

	interface ContentTemplate implements Node {
		id: ID!
		templateName: String
	}

	type DefaultTemplate implements ContentTemplate & Node {
		id: ID!
		templateName: String
	}

	type PageNoTitle implements ContentTemplate & Node {
		id: ID!
		templateName: String
	}

	type Plain {
		note: String
	}

	type Query {
		node: Node
		plain: Plain
	}
`;

// Navigate the panel to a type detail view via the schema search input —
// the same path a user takes.
function openType(name) {
	fireEvent.change(
		screen.getByRole('textbox', {
			name: 'Search GraphQL schema types and fields',
		}),
		{ target: { value: name } }
	);
	const typesGroup = screen
		.getByText('Types')
		.closest('.wpgraphql-ide-docs-search-group');
	fireEvent.click(within(typesGroup).getByRole('button', { name }));
}

function section(title) {
	const el = screen
		.queryByText(title)
		?.closest('.wpgraphql-ide-docs-section');
	return el ? within(el) : null;
}

// The collapse/expand toggle button for a section heading.
function sectionToggle(title) {
	return screen.getByText(title).closest('button');
}

describe('DocsExplorerPanel — interface relationships', () => {
	beforeEach(() => {
		__dep.schema = buildSchema(SDL);
		__dep.docsNavTarget = null;
		__dep.setDocsNavTarget.mockReset();
	});

	it('shows an Implements section on an object type, linking each interface', () => {
		render(<DocsExplorerPanel />);
		openType('DefaultTemplate');

		const implementsSection = section('Implements');
		expect(implementsSection).not.toBeNull();
		expect(
			implementsSection.getByRole('button', { name: 'ContentTemplate' })
		).toBeInTheDocument();
		expect(
			implementsSection.getByRole('button', { name: 'Node' })
		).toBeInTheDocument();
	});

	it('shows Implements and Implementations sections on an interface type', () => {
		render(<DocsExplorerPanel />);
		openType('ContentTemplate');

		// ContentTemplate implements Node…
		const implementsSection = section('Implements');
		expect(implementsSection).not.toBeNull();
		expect(
			implementsSection.getByRole('button', { name: 'Node' })
		).toBeInTheDocument();

		// …and is implemented by the two templates.
		const implementationsSection = section('Implementations');
		expect(implementationsSection).not.toBeNull();
		expect(
			implementationsSection.getByRole('button', {
				name: 'DefaultTemplate',
			})
		).toBeInTheDocument();
		expect(
			implementationsSection.getByRole('button', { name: 'PageNoTitle' })
		).toBeInTheDocument();
	});

	it('lists interfaces that implement an interface among its implementations', () => {
		render(<DocsExplorerPanel />);
		openType('Node');

		const implementationsSection = section('Implementations');
		expect(implementationsSection).not.toBeNull();
		// Objects and the implementing interface both appear.
		expect(
			implementationsSection.getByRole('button', {
				name: 'ContentTemplate',
			})
		).toBeInTheDocument();
		expect(
			implementationsSection.getByRole('button', {
				name: 'DefaultTemplate',
			})
		).toBeInTheDocument();
	});

	it('navigates to the interface when an Implements link is clicked', () => {
		render(<DocsExplorerPanel />);
		openType('DefaultTemplate');

		fireEvent.click(
			section('Implements').getByRole('button', {
				name: 'ContentTemplate',
			})
		);

		// Now on the ContentTemplate detail view — it has its own
		// Implementations section.
		expect(
			screen.getByText('ContentTemplate', {
				selector: '.wpgraphql-ide-docs-type-name',
			})
		).toBeInTheDocument();
		expect(section('Implementations')).not.toBeNull();
	});

	it('navigates to the implementing type when an Implementations link is clicked', () => {
		render(<DocsExplorerPanel />);
		openType('ContentTemplate');

		fireEvent.click(
			section('Implementations').getByRole('button', {
				name: 'PageNoTitle',
			})
		);

		expect(
			screen.getByText('PageNoTitle', {
				selector: '.wpgraphql-ide-docs-type-name',
			})
		).toBeInTheDocument();
	});

	it('omits both sections for types with no interface relationships', () => {
		render(<DocsExplorerPanel />);
		openType('Plain');

		expect(screen.queryByText('Implements')).not.toBeInTheDocument();
		expect(screen.queryByText('Implementations')).not.toBeInTheDocument();
	});
});

describe('DocsExplorerPanel — click affordances', () => {
	beforeEach(() => {
		__dep.schema = buildSchema(SDL);
		__dep.docsNavTarget = null;
		__dep.setDocsNavTarget.mockReset();
	});

	// The whole row is tinted on hover, so the whole row has to be the
	// button. Previously the row was an inert div wrapping a smaller button,
	// so most of the highlighted area did nothing when clicked.
	it('makes the entire type entry row the click target', () => {
		render(<DocsExplorerPanel />);
		openType('ContentTemplate');

		const row = section('Implementations').getByRole('button', {
			name: 'PageNoTitle',
		});
		expect(row).toHaveClass('wpgraphql-ide-docs-type-entry');

		// The kind badge is inside that same button, not a sibling of it —
		// clicking it navigates rather than landing on dead space.
		expect(
			within(row).getByText('object', { exact: false })
		).toBeInTheDocument();
	});

	// A field row has nowhere to navigate to, so it must not be a button or
	// announce itself as one; only the type badge and args toggle inside it
	// are interactive.
	it('leaves field rows non-interactive', () => {
		render(<DocsExplorerPanel />);
		openType('DefaultTemplate');

		const fieldName = screen.getByText('templateName', {
			selector: '.wpgraphql-ide-docs-field-name',
		});
		const fieldRow = fieldName.closest('.wpgraphql-ide-docs-field');

		expect(fieldRow).not.toBeNull();
		expect(fieldRow.tagName).toBe('DIV');
		expect(fieldRow).not.toHaveAttribute('role');
		expect(fieldRow).not.toHaveAttribute('onclick');
	});
});

describe('DocsExplorerPanel — collapsible sections', () => {
	beforeEach(() => {
		__dep.schema = buildSchema(SDL);
		__dep.docsNavTarget = null;
		__dep.setDocsNavTarget.mockReset();
	});

	it('sections start expanded and show an entry count', () => {
		render(<DocsExplorerPanel />);
		openType('DefaultTemplate');

		const toggle = sectionToggle('Implements');
		expect(toggle).toHaveAttribute('aria-expanded', 'true');
		// DefaultTemplate implements ContentTemplate & Node.
		expect(within(toggle).getByText('2')).toBeInTheDocument();
	});

	it('collapses and re-expands a relationship section', () => {
		render(<DocsExplorerPanel />);
		openType('DefaultTemplate');

		const toggle = sectionToggle('Implements');
		fireEvent.click(toggle);
		expect(toggle).toHaveAttribute('aria-expanded', 'false');
		expect(
			screen.queryByRole('button', { name: 'ContentTemplate' })
		).not.toBeInTheDocument();

		fireEvent.click(toggle);
		expect(toggle).toHaveAttribute('aria-expanded', 'true');
		expect(
			screen.getByRole('button', { name: 'ContentTemplate' })
		).toBeInTheDocument();
	});

	it('collapses the Fields section', () => {
		render(<DocsExplorerPanel />);
		openType('DefaultTemplate');

		expect(screen.getByText('templateName')).toBeInTheDocument();

		fireEvent.click(sectionToggle('Fields'));
		expect(screen.queryByText('templateName')).not.toBeInTheDocument();
	});

	it('resets collapse state to expanded when navigating to another type', () => {
		render(<DocsExplorerPanel />);
		openType('DefaultTemplate');

		fireEvent.click(sectionToggle('Implements'));
		expect(sectionToggle('Implements')).toHaveAttribute(
			'aria-expanded',
			'false'
		);

		// A different type's view starts expanded…
		openType('PageNoTitle');
		expect(sectionToggle('Implements')).toHaveAttribute(
			'aria-expanded',
			'true'
		);

		// …and so does the original type when revisited via Back.
		fireEvent.click(screen.getByRole('button', { name: 'Back' }));
		expect(
			screen.getByText('DefaultTemplate', {
				selector: '.wpgraphql-ide-docs-type-name',
			})
		).toBeInTheDocument();
		expect(sectionToggle('Implements')).toHaveAttribute(
			'aria-expanded',
			'true'
		);
	});
});
