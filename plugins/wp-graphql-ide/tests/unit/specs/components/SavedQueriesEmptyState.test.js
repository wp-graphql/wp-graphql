/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

import { SavedQueriesEmptyState } from '../../../../src/components/SavedQueriesPanel';

describe('SavedQueriesEmptyState', () => {
	test('renders the save hint, the import button, and the seeds footnote', () => {
		render(<SavedQueriesEmptyState onImportClick={() => {}} />);

		expect(
			screen.getByText(
				/Use the toolbar Save button to save the current query/i
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole('button', { name: /Import queries from JSON/i })
		).toBeInTheDocument();
		expect(
			screen.getByText(/Import the sample JSON from the plugin/i)
		).toBeInTheDocument();
	});

	test('clicking the import button invokes onImportClick', () => {
		const onImportClick = jest.fn();
		render(<SavedQueriesEmptyState onImportClick={onImportClick} />);

		fireEvent.click(
			screen.getByRole('button', { name: /Import queries from JSON/i })
		);

		expect(onImportClick).toHaveBeenCalledTimes(1);
	});
});
