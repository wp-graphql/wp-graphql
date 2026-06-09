import { renderHook, act } from '@testing-library/react';
import { useAutoSave } from '../../../../src/hooks/useAutoSave';

// Regression coverage for the tab-title freeze: a temp draft must never
// persist a derived title mid-type. The tab derives live from the query
// while the title is auto, so persisting "G" (captured when a body brace
// already follows the partially-typed op name) froze the tab at the first
// character instead of tracking through to "GetPosts".
describe('useAutoSave — temp draft title', () => {
	const setup = (activeDocument) => {
		const saveDocument = jest.fn();
		const noop = jest.fn();
		const { result } = renderHook(() =>
			useAutoSave({
				activeDocument,
				saveDocument,
				setQuery: noop,
				setVariables: noop,
				setHeaders: noop,
				setDocSettingsValues: noop,
			})
		);
		return { saveDocument, result };
	};

	it('does not freeze a title while typing the op name (body brace present)', () => {
		// The pre-existing `{` is what made deriveStableDocTitle match "G".
		const { saveDocument, result } = setup({
			id: 'temp-1',
			title: '',
		});

		act(() => result.current.scheduleAutoSave('query', 'query G{}'));

		expect(saveDocument).toHaveBeenCalledTimes(1);
		const payload = saveDocument.mock.calls[0][1];
		expect(payload).toEqual({ query: 'query G{}' });
		expect(payload.title).toBeUndefined();
	});

	it('still persists the full query for a temp draft synchronously', () => {
		const { saveDocument, result } = setup({
			id: 'temp-1',
			title: '',
		});

		const query = 'query GetPosts { posts { nodes { id } } }';
		act(() => result.current.scheduleAutoSave('query', query));

		expect(saveDocument).toHaveBeenCalledWith('temp-1', { query });
		expect(saveDocument.mock.calls[0][1].title).toBeUndefined();
	});
});
