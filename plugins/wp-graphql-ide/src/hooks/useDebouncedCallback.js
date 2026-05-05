import { useCallback, useEffect, useRef } from 'react';

/**
 * Debounce a callback. Each call resets a `delay`-ms timer; the wrapped
 * function fires only when calls stop landing. The latest argument set
 * wins. The returned `cancel` clears any pending fire (use it on unmount
 * or when discarding state — e.g. tab close before autosave fires).
 *
 * The callback is stored in a ref so the wrapper has stable identity
 * across renders; consumers don't need to memoize the function they
 * pass in.
 *
 * @param {Function} fn    Callback to debounce.
 * @param {number}   delay Idle window in milliseconds before fn runs.
 * @return {[Function, Function]} `[debounced, cancel]`
 */
export function useDebouncedCallback(fn, delay) {
	const fnRef = useRef(fn);
	const timerRef = useRef(null);

	useEffect(() => {
		fnRef.current = fn;
	}, [fn]);

	const cancel = useCallback(() => {
		if (timerRef.current) {
			clearTimeout(timerRef.current);
			timerRef.current = null;
		}
	}, []);

	const debounced = useCallback(
		(...args) => {
			cancel();
			timerRef.current = setTimeout(() => {
				timerRef.current = null;
				fnRef.current(...args);
			}, delay);
		},
		[cancel, delay]
	);

	useEffect(() => cancel, [cancel]);

	return [debounced, cancel];
}
