import { useCallback, useState } from 'react';

/**
 * Local-state Set with a `toggle(id)` action — the membership-flip idiom
 * (`set.has(id) ? delete : add`) is identical everywhere it appears, and
 * lifting it out keeps callsites focused on the domain (which collection
 * is picked, which item is selected) rather than Set bookkeeping.
 *
 * `initial` is captured lazily on mount; later changes to it are NOT
 * reflected. This matches React's standard `useState(initial)` semantics
 * and is appropriate when the consumer is mounted fresh per session
 * (e.g. a dialog opened on demand). If a parent loads `initial`
 * asynchronously, mount the consumer behind a guard that waits for the
 * data so the first render gets the real seed.
 *
 * @param {Iterable} initial Initial members.
 * @return {[Set, (id: any) => void]} `[set, toggle]`
 */
export function useToggleSet(initial = []) {
	const [set, setSet] = useState(() => new Set(initial));

	const toggle = useCallback((id) => {
		setSet((prev) => {
			const next = new Set(prev);
			if (next.has(id)) {
				next.delete(id);
			} else {
				next.add(id);
			}
			return next;
		});
	}, []);

	return [set, toggle];
}
