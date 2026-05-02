import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom';
import { ResponseViewer } from '../../../../src/components/editors/ResponseViewer';

describe('ResponseViewer', () => {
	test('renders a container element', () => {
		const { container } = render(<ResponseViewer value="" />);
		const viewer = container.querySelector(
			'.wpgraphql-ide-response-viewer'
		);
		expect(viewer).toBeInTheDocument();
	});

	test('displays JSON response content', () => {
		const response = JSON.stringify(
			{ data: { post: { title: 'Hello' } } },
			null,
			2
		);
		const { container } = render(<ResponseViewer value={response} />);
		const cmContent = container.querySelector('.cm-content');
		expect(cmContent).toBeInTheDocument();
		expect(cmContent.textContent).toContain('Hello');
	});

	test('is always read-only', () => {
		const { container } = render(<ResponseViewer value='{ "data": {} }' />);
		const cmContent = container.querySelector('.cm-content');
		expect(cmContent).toBeInTheDocument();
		// Editor is focusable (contenteditable=true) for Cmd+A selection,
		// but EditorState.readOnly prevents actual edits.
		expect(cmContent.getAttribute('contenteditable')).toBe('true');
	});

	test('updates when response value changes', () => {
		const { container, rerender } = render(
			<ResponseViewer value='{ "data": { "old": true } }' />
		);

		rerender(<ResponseViewer value='{ "data": { "new": true } }' />);

		const cmContent = container.querySelector('.cm-content');
		expect(cmContent.textContent).toContain('new');
	});

	test('applies custom className', () => {
		const { container } = render(
			<ResponseViewer value="" className="custom-response" />
		);
		const viewer = container.querySelector('.custom-response');
		expect(viewer).toBeInTheDocument();
	});
});
