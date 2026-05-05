import { useCallback, useState } from 'react';

/**
 * Local-state Set with a `toggle(id)` action — the membership-flip idiom
 * (`set.has(id) ? delete : add`) is identical everywhere it appears, and
 * lifting it out keeps callsites focused on the domain (which collection
 * is picked, which item is selected) rather than Set bookkeeping.
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
