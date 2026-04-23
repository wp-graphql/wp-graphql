import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom';
import { GraphQLEditor } from '../../../../src/components/editors/GraphQLEditor';

describe('GraphQLEditor', () => {
	test('renders a container element', () => {
		const { container } = render(
			<GraphQLEditor value="" onChange={() => {}} />
		);
		const editor = container.querySelector('.wpgraphql-ide-graphql-editor');
		expect(editor).toBeInTheDocument();
	});

	test('initializes CodeMirror with the provided value', () => {
		const { container } = render(
			<GraphQLEditor
				value={'{ posts { nodes { id } } }'}
				onChange={() => {}}
			/>
		);
		const cmContent = container.querySelector('.cm-content');
		expect(cmContent).toBeInTheDocument();
		expect(cmContent.textContent).toContain('posts');
	});

	test('initializes an editable CodeMirror instance', () => {
		const { container } = render(
			<GraphQLEditor value="" onChange={() => {}} />
		);

		const cmContent = container.querySelector('.cm-content');
		expect(cmContent).toBeInTheDocument();
		// Non-readOnly editor should be contenteditable.
		expect(cmContent.getAttribute('contenteditable')).toBe('true');
	});

	test('syncs when value prop changes externally', () => {
		const { container, rerender } = render(
			<GraphQLEditor value="{ old }" onChange={() => {}} />
		);

		rerender(<GraphQLEditor value="{ new }" onChange={() => {}} />);

		const cmContent = container.querySelector('.cm-content');
		expect(cmContent.textContent).toContain('new');
	});

	test('applies readOnly mode', () => {
		const { container } = render(
			<GraphQLEditor value="{ test }" onChange={() => {}} readOnly />
		);
		const cmContent = container.querySelector('.cm-content');
		expect(cmContent).toBeInTheDocument();
		// CM6 sets contenteditable=false when readOnly.
		expect(cmContent.getAttribute('contenteditable')).toBe('false');
	});

	test('applies custom className', () => {
		const { container } = render(
			<GraphQLEditor
				value=""
				onChange={() => {}}
				className="my-custom-class"
			/>
		);
		const editor = container.querySelector('.my-custom-class');
		expect(editor).toBeInTheDocument();
	});
});
