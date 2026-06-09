import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { DocumentSettingsDrawer } from '../../../../../src/components/document-settings/DocumentSettingsDrawer';

const FIELDS = [
	{
		name: 'description',
		label: 'Description',
		desc: '',
		type: 'textarea',
		default: '',
	},
	{
		name: 'aliases',
		label: 'Alias Names',
		desc: '',
		type: 'tag_list',
		default: [],
	},
];

function renderPanel(overrides = {}) {
	const onChange = jest.fn();
	const utils = render(
		<DocumentSettingsDrawer
			fields={FIELDS}
			values={{ description: 'hi', aliases: ['home'] }}
			onChange={onChange}
			globalGrantMode="public"
			{...overrides}
		/>
	);
	return { onChange, ...utils };
}

describe('DocumentSettingsDrawer', () => {
	it('renders all registered fields', () => {
		renderPanel();
		expect(screen.getByLabelText('Description')).toBeInTheDocument();
		expect(screen.getByLabelText('Alias Names')).toBeInTheDocument();
	});

	it('forwards textarea changes via onChange', () => {
		const { onChange } = renderPanel();
		fireEvent.change(screen.getByLabelText('Description'), {
			target: { value: 'updated' },
		});
		expect(onChange).toHaveBeenCalledWith('description', 'updated');
	});

	it('renders the same fields whether the doc is saved or temp', () => {
		// The panel is no longer gated on docId — values for unsaved docs
		// live in memory and ride along on first save.
		const { rerender } = renderPanel({ values: { description: 'a' } });
		expect(screen.getByLabelText('Description')).toHaveValue('a');

		rerender(
			<DocumentSettingsDrawer
				fields={FIELDS}
				values={{ description: 'b' }}
				onChange={() => {}}
				globalGrantMode="public"
			/>
		);
		expect(screen.getByLabelText('Description')).toHaveValue('b');
	});
});
