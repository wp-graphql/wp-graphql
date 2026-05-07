import { useCallback, useState } from 'react';

/**
 * Read a persisted size from localStorage with a sensible fallback.
 *
 * Numeric strings (px) parse to numbers; percent strings (`'70%'`)
 * pass through; missing or non-positive values fall back to `defaultValue`.
 *
 * @param {string}        key
 * @param {number|string} defaultValue
 *
 * @return {number|string}
 */
function readPersisted(key, defaultValue) {
	try {
		const raw = window.localStorage.getItem(key);
		if (!raw) {
			return defaultValue;
		}
		const asNumber = Number(raw);
		if (Number.isFinite(asNumber)) {
			return asNumber > 0 ? asNumber : defaultValue;
		}
		return raw;
	} catch {
		return defaultValue;
	}
}

/**
 * State backed by `localStorage`. The setter writes through on every
 * call so the consuming component doesn't need to know the storage key.
 *
 * Used for the IDE's resizable pane sizes (query width, editor height,
 * response viewer height) and the response view-mode toggle.
 *
 * @param {string} key
 * @param {*}      defaultValue
 * @param {Object} [opts]
 * @param {number} [opts.minPx] Clamp persisted px values to this minimum
 *                              when they read as a finite number. Lets
 *                              us recover from a previous tiny-drag or
 *                              stale flex-mode height that would leave
 *                              the pane unreadable on the next visit.
 *
 * @return {[*, Function]}
 */
export function usePersistedSize(key, defaultValue, opts = {}) {
	const { minPx } = opts;
	const [value, setValue] = useState(() => {
		const stored = readPersisted(key, defaultValue);
		if (typeof stored === 'number' && typeof minPx === 'number') {
			return Math.max(minPx, stored);
		}
		return stored;
	});
	const setPersisted = useCallback(
		(next) => {
			setValue(next);
			try {
				window.localStorage.setItem(key, String(next));
			} catch {
				// ignore
			}
		},
		[key]
	);
	return [value, setPersisted];
}
