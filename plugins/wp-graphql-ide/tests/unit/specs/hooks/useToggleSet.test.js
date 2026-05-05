import { renderHook, act } from '@testing-library/react';
import { useToggleSet } from '../../../../src/hooks/useToggleSet';

describe('useToggleSet', () => {
	it('starts empty by default', () => {
		const { result } = renderHook(() => useToggleSet());
		const [set] = result.current;
		expect(set).toBeInstanceOf(Set);
		expect(set.size).toBe(0);
	});

	it('seeds membership from `initial`', () => {
		const { result } = renderHook(() => useToggleSet([1, 2, 3]));
		const [set] = result.current;
		expect(set.has(1)).toBe(true);
		expect(set.has(2)).toBe(true);
		expect(set.has(3)).toBe(true);
	});

	it('toggle(id) flips membership in/out', () => {
		const { result } = renderHook(() => useToggleSet());
		const [, toggle] = result.current;

		act(() => toggle('a'));
		expect(result.current[0].has('a')).toBe(true);

		act(() => toggle('a'));
		expect(result.current[0].has('a')).toBe(false);
	});

	it('returns a new Set on every change (immutable semantics)', () => {
		const { result } = renderHook(() => useToggleSet([1]));
		const initialSet = result.current[0];
		const [, toggle] = result.current;

		act(() => toggle(2));
		expect(result.current[0]).not.toBe(initialSet);
		expect(initialSet.has(2)).toBe(false);
		expect(result.current[0].has(2)).toBe(true);
	});

	it('does not reflect later changes to `initial` (matches useState)', () => {
		// Pinning the lazy-initial behavior is intentional — the docstring
		// promises useState semantics so callers can rely on them.
		const { result, rerender } = renderHook(
			({ items }) => useToggleSet(items),
			{ initialProps: { items: [1] } }
		);

		expect(result.current[0].has(1)).toBe(true);
		rerender({ items: [99] });
		expect(result.current[0].has(99)).toBe(false);
		expect(result.current[0].has(1)).toBe(true);
	});

	it('toggle has stable identity across renders', () => {
		const { result, rerender } = renderHook(() => useToggleSet());
		const firstToggle = result.current[1];
		rerender();
		const secondToggle = result.current[1];
		expect(firstToggle).toBe(secondToggle);
	});
});
