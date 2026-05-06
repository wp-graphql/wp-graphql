import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { RadioWithDefaultField } from '../../../../../src/components/document-settings/fields/RadioWithDefaultField';

const field = {
	name: 'grant',
	label: 'Allow / Deny',
	desc: '',
	options: [
		{ value: 'allow', label: 'Allowed' },
		{ value: 'deny', label: 'Deny' },
		{ value: '', label: 'Use global default' },
	],
};

describe('RadioWithDefaultField', () => {
	it('renders all three options', () => {
		render(
			<RadioWithDefaultField field={field} value="" onChange={() => {}} />
		);
		expect(screen.getByLabelText('Allowed')).toBeInTheDocument();
		expect(screen.getByLabelText('Deny')).toBeInTheDocument();
	});

	it('annotates the default option label with the global default', () => {
		render(
			<RadioWithDefaultField
				field={field}
				value=""
				onChange={() => {}}
				globalDefault="some_denied"
			/>
		);
		// some_denied → effective default behavior is "Allowed".
		expect(
			screen.getByLabelText('Use global default (Allowed)')
		).toBeInTheDocument();
	});

	it('reflects the selected value', () => {
		render(
			<RadioWithDefaultField
				field={field}
				value="deny"
				onChange={() => {}}
			/>
		);
		expect(screen.getByLabelText('Deny')).toBeChecked();
	});

	it('calls onChange with the selected value', () => {
		const onChange = jest.fn();
		render(
			<RadioWithDefaultField field={field} value="" onChange={onChange} />
		);
		fireEvent.click(screen.getByLabelText('Allowed'));
		expect(onChange).toHaveBeenCalledWith('allow');
	});
});
