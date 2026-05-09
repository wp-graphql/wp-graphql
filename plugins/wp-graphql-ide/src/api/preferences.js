import apiFetch from '@wordpress/api-fetch';

/**
 * Scope-aware preferences adapter.
 *
 * Each preference key declares a `scope` that decides where it lives:
 *
 * - `device` — localStorage on this browser/render-context. Cheap to
 *   write, never bothers the server, works for anonymous endpoint
 *   visitors. Used for UI chrome (panel widths, which panel is open,
 *   open tabs, view modes — anything where the cross-device "feature"
 *   was theoretical and the persistence failure mode (anonymous
 *   endpoint can't write to user meta) was real).
 * - `user` — server user-meta via REST. Cross-device for the logged-in
 *   user, by definition logged-in only. Reserved for identity-bound
 *   data: personal collections, sort modes against those collections,
 *   notification de-duplication.
 *
 * Promote a key from `device` to `user` if and when "I want this
 * preference to follow me to other browsers" becomes a real complaint.
 * Demote in the opposite direction if "anonymous endpoint can't keep
 * this" or "this resets when my session expires" becomes a complaint.
 *
 * Unknown keys default to `user` to preserve the previous behavior of
 * `savePreference` (server REST write) for any caller not yet on this
 * map. New keys should add an entry rather than rely on the default.
 */

const META_PREFIX = 'wpgraphql_ide_';
const USER_ENDPOINT = '/wp/v2/users/me';
const STORAGE_VERSION = 'v1';

const SCOPES = Object.freeze({
	// Device-scoped UI chrome.
	response_view_mode: 'device',
	panel_order: 'device',
	left_panel: 'device',
	open_tabs: 'device',
	active_tab: 'device',
	visible_panel: 'device',

	// User-scoped, identity-bound. Logged-in only.
	personal_collections: 'user',
	collection_sort_modes: 'user',
	seen_shared_collections: 'user',
});

/**
 * @param {string} key
 * @return {'device' | 'user'}
 */
export function scopeOf(key) {
	return SCOPES[key] || 'user';
}

function deviceStorageKey() {
	const data =
		typeof window !== 'undefined' ? window.WPGRAPHQL_IDE_DATA : null;
	const id = (data && Number(data?.context?.currentUserId)) || 0;
	const ctx = data?.endpointMode ? 'endpoint' : 'admin';
	return `wpgraphql-ide:prefs:${STORAGE_VERSION}:user-${id}:ctx-${ctx}`;
}

function readDeviceBucket() {
	if (typeof window === 'undefined') {
		return {};
	}
	try {
		const raw = window.localStorage.getItem(deviceStorageKey());
		if (!raw) {
			return {};
		}
		const parsed = JSON.parse(raw);
		return parsed && typeof parsed === 'object' ? parsed : {};
	} catch {
		return {};
	}
}

function writeDeviceBucket(bucket) {
	if (typeof window === 'undefined') {
		return;
	}
	try {
		window.localStorage.setItem(
			deviceStorageKey(),
			JSON.stringify(bucket || {})
		);
	} catch {
		// localStorage unavailable / quota exhausted / Safari private mode.
		// Swallow — the in-memory state still reflects the change for the
		// current session; we just lose it on refresh.
	}
}

async function readServerPrefs() {
	try {
		const user = await apiFetch({
			path: `${USER_ENDPOINT}?_fields=meta`,
		});
		const meta = user?.meta || {};
		const prefs = {};
		for (const [k, v] of Object.entries(meta)) {
			if (k.startsWith(META_PREFIX)) {
				prefs[k.replace(META_PREFIX, '')] = v;
			}
		}
		return prefs;
	} catch {
		return {};
	}
}

async function writeServerPref(key, value) {
	return apiFetch({
		path: USER_ENDPOINT,
		method: 'POST',
		data: {
			meta: {
				[`${META_PREFIX}${key}`]: value,
			},
		},
	});
}

/**
 * Read every preference for the current surface. Merges server meta
 * (user-scoped) and the localStorage bucket (device-scoped) into a
 * single flat object keyed by pref name (without the `wpgraphql_ide_`
 * prefix). Device values take precedence so a stale server value left
 * over from before a key was demoted to `device` doesn't reappear.
 *
 * @return {Promise<Object>}
 */
export async function getPreferences() {
	const [server, device] = await Promise.all([
		readServerPrefs(),
		Promise.resolve(readDeviceBucket()),
	]);
	return { ...server, ...device };
}

/**
 * Read a single preference. Routes by scope.
 *
 * @param {string} key
 * @return {Promise<*>}
 */
export async function getPreference(key) {
	if (scopeOf(key) === 'device') {
		return readDeviceBucket()[key];
	}
	const all = await readServerPrefs();
	return all[key];
}

/**
 * Synchronous read for `device`-scope keys, for places where useState
 * lazy init or other sync paths can't await. Returns `undefined` for
 * `user`-scope keys (those require a server round-trip).
 *
 * @param {string} key
 * @return {*}
 */
export function readDevicePreference(key) {
	if (scopeOf(key) !== 'device') {
		return undefined;
	}
	return readDeviceBucket()[key];
}

/**
 * Write a single preference. Routes by scope. Always returns a
 * Promise so callers can treat both scopes uniformly (the device
 * write resolves immediately).
 *
 * @param {string} key
 * @param {*}      value
 * @return {Promise<*>}
 */
export async function setPreference(key, value) {
	if (scopeOf(key) === 'device') {
		const bucket = readDeviceBucket();
		bucket[key] = value;
		writeDeviceBucket(bucket);
		return;
	}
	return writeServerPref(key, value);
}
