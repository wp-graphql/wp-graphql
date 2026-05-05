/**
 * Thin localStorage helpers. Every callsite that touched localStorage
 * directly was already wrapping it in try/catch (Safari private mode,
 * quota errors, the rare admin running with site data disabled), so
 * lifting that to one place removes the boilerplate and keeps the
 * fallback semantics consistent.
 *
 * Reads return the default on any failure; writes swallow failures
 * silently — durability is best-effort and the IDE always works in
 * memory either way.
 */

export function getStorageItem(key, defaultValue = null) {
	try {
		const v = window.localStorage.getItem(key);
		return v === null ? defaultValue : v;
	} catch {
		return defaultValue;
	}
}

export function setStorageItem(key, value) {
	try {
		window.localStorage.setItem(key, String(value));
	} catch {
		// localStorage unavailable or quota exceeded.
	}
}

export function removeStorageItem(key) {
	try {
		window.localStorage.removeItem(key);
	} catch {
		// localStorage unavailable.
	}
}

export function getStorageJSON(key, defaultValue) {
	const raw = getStorageItem(key);
	if (raw === null || raw === undefined) {
		return defaultValue;
	}
	let parsed;
	try {
		parsed = JSON.parse(raw);
	} catch {
		return defaultValue;
	}
	// A literal `null` payload should also fall back so callers can rely
	// on the typed default they passed in.
	return parsed === null || parsed === undefined ? defaultValue : parsed;
}

export function setStorageJSON(key, value) {
	let serialized;
	try {
		serialized = JSON.stringify(value);
	} catch {
		// JSON.stringify can throw on circular refs; drop the write
		// rather than corrupt the stored payload.
		return;
	}
	setStorageItem(key, serialized);
}
