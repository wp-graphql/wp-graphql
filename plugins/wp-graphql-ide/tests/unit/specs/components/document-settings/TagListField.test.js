import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { TagListField } from '../../../../../src/components/document-settings/fields/TagListField';

const field = {
	name: 'aliases',
	label: 'Alias Names',
	desc: '',
};

function renderWithChange(initialValue = []) {
	const onChange = jest.fn();
	const utils = render(
		<TagListField field={field} value={initialValue} onChange={onChange} />
	);
	return { onChange, ...utils };
}

describe('TagListField', () => {
	it('renders existing tags as chips', () => {
		renderWithChange(['home', 'feed']);
		expect(screen.getByText('home')).toBeInTheDocument();
		expect(screen.getByText('feed')).toBeInTheDocument();
	});

	it('commits a new tag on Enter', () => {
		const { onChange } = renderWithChange(['existing']);
		const input = screen.getByLabelText('Alias Names');
		fireEvent.change(input, { target: { value: 'new-tag' } });
		fireEvent.keyDown(input, { key: 'Enter' });
		expect(onChange).toHaveBeenCalledWith(['existing', 'new-tag']);
	});

	it('commits on comma key', () => {
		const { onChange } = renderWithChange([]);
		const input = screen.getByLabelText('Alias Names');
		fireEvent.change(input, { target: { value: 'first' } });
		fireEvent.keyDown(input, { key: ',' });
		expect(onChange).toHaveBeenCalledWith(['first']);
	});

	it('drops duplicates without calling onChange', () => {
		const { onChange } = renderWithChange(['dupe']);
		const input = screen.getByLabelText('Alias Names');
		fireEvent.change(input, { target: { value: 'dupe' } });
		fireEvent.keyDown(input, { key: 'Enter' });
		expect(onChange).not.toHaveBeenCalled();
	});

	it('trims whitespace on commit', () => {
		const { onChange } = renderWithChange([]);
		const input = screen.getByLabelText('Alias Names');
		fireEvent.change(input, { target: { value: '  spaced  ' } });
		fireEvent.keyDown(input, { key: 'Enter' });
		expect(onChange).toHaveBeenCalledWith(['spaced']);
	});

	it('removes a tag when its × is clicked', () => {
		const { onChange } = renderWithChange(['a', 'b', 'c']);
		fireEvent.click(screen.getByLabelText('Remove b'));
		expect(onChange).toHaveBeenCalledWith(['a', 'c']);
	});

	it('removes the last tag on Backspace when input is empty', () => {
		const { onChange } = renderWithChange(['only']);
		const input = screen.getByLabelText('Alias Names');
		fireEvent.keyDown(input, { key: 'Backspace' });
		expect(onChange).toHaveBeenCalledWith([]);
	});
});
