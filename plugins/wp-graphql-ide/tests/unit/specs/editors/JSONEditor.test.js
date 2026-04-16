import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom';
import { JSONEditor } from '../../../../src/components/editors/JSONEditor';

describe('JSONEditor', () => {
	test('renders a container element', () => {
		const { container } = render(
			<JSONEditor value="" onChange={() => {}} />
		);
		const editor = container.querySelector('.wpgraphql-ide-json-editor');
		expect(editor).toBeInTheDocument();
	});

	test('initializes CodeMirror with JSON content', () => {
		const jsonValue = '{ "key": "value" }';
		const { container } = render(
			<JSONEditor value={jsonValue} onChange={() => {}} />
		);
		const cmContent = container.querySelector('.cm-content');
		expect(cmContent).toBeInTheDocument();
		expect(cmContent.textContent).toContain('key');
	});

	test('syncs when value prop changes externally', () => {
		const { container, rerender } = render(
			<JSONEditor value='{ "a": 1 }' onChange={() => {}} />
		);

		rerender(<JSONEditor value='{ "b": 2 }' onChange={() => {}} />);

		const cmContent = container.querySelector('.cm-content');
		expect(cmContent.textContent).toContain('b');
	});

	test('hides editor when isHidden is true', () => {
		const { container } = render(
			<JSONEditor value="" onChange={() => {}} isHidden />
		);
		const editor = container.querySelector('.wpgraphql-ide-json-editor');
		expect(editor).toHaveStyle('display: none');
	});

	test('shows editor when isHidden is false', () => {
		const { container } = render(
			<JSONEditor value="" onChange={() => {}} isHidden={false} />
		);
		const editor = container.querySelector('.wpgraphql-ide-json-editor');
		expect(editor).not.toHaveStyle('display: none');
	});
});
