import { renderHook, act } from '@testing-library/react';
import { useAutoSave } from '../../../../src/hooks/useAutoSave';

// Regression guard: tab title used to lock at the first character of
// the operation name. See the sticky-title comment in useAutoSave.js.
describe('useAutoSave — title persistence on query change', () => {
	const baseProps = {
		setQuery: jest.fn(),
		setVariables: jest.fn(),
		setHeaders: jest.fn(),
		setDocSettingsValues: jest.fn(),
	};

	it('does not persist a derived title for temp drafts', () => {
		const saveDocument = jest.fn();
		const { result } = renderHook(() =>
			useAutoSave({
				...baseProps,
				activeDocument: { id: 'temp-1', title: 'Untitled', query: '' },
				saveDocument,
			})
		);

		act(() => result.current.scheduleAutoSave('query', 'query G {'));

		expect(saveDocument).toHaveBeenCalledTimes(1);
		const payload = saveDocument.mock.calls[0][1];
		expect(payload.query).toBe('query G {');
		expect(payload).not.toHaveProperty('title');
	});

	it('persists the derived title on the debounced save for saved docs', () => {
		jest.useFakeTimers();
		const saveDocument = jest.fn();
		const { result } = renderHook(() =>
			useAutoSave({
				...baseProps,
				activeDocument: { id: 42, title: 'Untitled', query: '' },
				saveDocument,
			})
		);

		act(() =>
			result.current.scheduleAutoSave(
				'query',
				'query GetPosts { posts { id } }'
			)
		);
		act(() => jest.runAllTimers());
		jest.useRealTimers();

		expect(saveDocument).toHaveBeenCalledTimes(1);
		expect(saveDocument.mock.calls[0][1].title).toBe('GetPosts');
	});
});
