import { renderHook, act } from '@testing-library/react';
import { useDebouncedCallback } from '../../../../src/hooks/useDebouncedCallback';

describe('useDebouncedCallback', () => {
	beforeEach(() => {
		jest.useFakeTimers();
	});

	afterEach(() => {
		jest.useRealTimers();
	});

	it('fires after the idle window elapses', () => {
		const fn = jest.fn();
		const { result } = renderHook(() => useDebouncedCallback(fn, 200));
		const [debounced] = result.current;

		act(() => debounced('a'));
		expect(fn).not.toHaveBeenCalled();

		act(() => jest.advanceTimersByTime(200));
		expect(fn).toHaveBeenCalledTimes(1);
		expect(fn).toHaveBeenCalledWith('a');
	});

	it('coalesces rapid calls into a single fire with the latest args', () => {
		const fn = jest.fn();
		const { result } = renderHook(() => useDebouncedCallback(fn, 100));
		const [debounced] = result.current;

		act(() => {
			debounced(1);
			debounced(2);
			debounced(3);
		});

		act(() => jest.advanceTimersByTime(100));
		expect(fn).toHaveBeenCalledTimes(1);
		expect(fn).toHaveBeenCalledWith(3);
	});

	it('cancel() prevents a pending fire', () => {
		const fn = jest.fn();
		const { result } = renderHook(() => useDebouncedCallback(fn, 100));
		const [debounced, cancel] = result.current;

		act(() => debounced('a'));
		act(() => cancel());
		act(() => jest.advanceTimersByTime(500));
		expect(fn).not.toHaveBeenCalled();
	});

	it('cancels any pending fire on unmount', () => {
		const fn = jest.fn();
		const { result, unmount } = renderHook(() =>
			useDebouncedCallback(fn, 100)
		);
		const [debounced] = result.current;

		act(() => debounced('a'));
		unmount();
		act(() => jest.advanceTimersByTime(500));
		expect(fn).not.toHaveBeenCalled();
	});

	it('uses the latest fn when re-rendered with a new callback', () => {
		const first = jest.fn();
		const second = jest.fn();
		const { result, rerender } = renderHook(
			({ cb }) => useDebouncedCallback(cb, 100),
			{ initialProps: { cb: first } }
		);

		const [debounced] = result.current;
		rerender({ cb: second });

		act(() => debounced('a'));
		act(() => jest.advanceTimersByTime(100));

		expect(first).not.toHaveBeenCalled();
		expect(second).toHaveBeenCalledWith('a');
	});
});
