/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

import { SettingsField } from '../../../../../src/components/settings/SettingsField';

describe('SettingsField', () => {
	it('renders a text input for fields with type=text', () => {
		render(
			<SettingsField
				field={{ name: 'foo', type: 'text', label: 'Foo' }}
				value="bar"
				onChange={() => {}}
			/>
		);
		const input = screen.getByLabelText('Foo');
		expect(input).toHaveValue('bar');
	});

	it('renders a divider block from captured HTML for type=custom fields', () => {
		const html =
			'<h2>Network Cache Settings</h2><p>Below are settings…</p>';
		render(
			<SettingsField
				field={{ name: 'network_cache_notice', type: 'custom', html }}
				value={null}
				onChange={() => {}}
			/>
		);
		const heading = screen.getByRole('heading', {
			name: /Network Cache Settings/,
		});
		expect(heading).toBeInTheDocument();
		expect(screen.getByText(/Below are settings/)).toBeInTheDocument();
		expect(document.querySelector('input[type="text"]')).toBeNull();
	});

	it('renders nothing when a custom field has no captured HTML', () => {
		const { container } = render(
			<SettingsField
				field={{ name: 'empty_notice', type: 'custom' }}
				value={null}
				onChange={() => {}}
			/>
		);
		expect(container.firstChild).toBeNull();
	});

	it('renders a number input that emits numeric onChange values', () => {
		const onChange = jest.fn();
		render(
			<SettingsField
				field={{ name: 'ttl', type: 'number', label: 'TTL' }}
				value={600}
				onChange={onChange}
			/>
		);
		const input = screen.getByLabelText('TTL');
		fireEvent.change(input, { target: { value: '900' } });
		expect(onChange).toHaveBeenCalledWith(900);
	});
});
