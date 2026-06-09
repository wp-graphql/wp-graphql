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

// Built-in preference scope map. Plugins extend this at runtime via
// `registerPreference`. Frozen so direct mutation of the seed map is
// rejected; the live registry below is a Map so it stays mutable.
const BUILT_IN_SCOPES = Object.freeze({
	// Device-scoped UI chrome.
	response_view_mode: 'device',
	response_tab_order: 'device',
	panel_order: 'device',
	left_panel: 'device',
	open_tabs: 'device',
	active_tab: 'device',
	visible_panel: 'device',
	editor_bottom_collapsed: 'device',
	editor_bottom_active_tab: 'device',
	response_bottom_collapsed: 'device',
	response_bottom_active_tab: 'device',
	settings_active_section: 'device',

	// User-scoped, identity-bound. Logged-in only.
	personal_collections: 'user',
	collection_sort_modes: 'user',
	collection_order: 'user',
	seen_shared_collections: 'user',
	collapsed_notices: 'user',
	section_states: 'user',
});

/**
 * Public constants for the built-in preference keys. Use these instead
 * of bare string literals at callsites — typos become compile-time
 * errors, autocomplete works, and a future rename is a single edit
 * here rather than a grep-and-pray sweep.
 *
 * Plugins registering their own prefs add to the `WPGraphQLIDE.PreferenceKeys`
 * surface at runtime via `registerPreference`; this constant only covers
 * the built-ins.
 */
export const PREFERENCE_KEYS = Object.freeze({
	// Device-scoped UI chrome.
	RESPONSE_VIEW_MODE: 'response_view_mode',
	RESPONSE_TAB_ORDER: 'response_tab_order',
	PANEL_ORDER: 'panel_order',
	LEFT_PANEL: 'left_panel',
	OPEN_TABS: 'open_tabs',
	ACTIVE_TAB: 'active_tab',
	VISIBLE_PANEL: 'visible_panel',
	EDITOR_BOTTOM_COLLAPSED: 'editor_bottom_collapsed',
	EDITOR_BOTTOM_ACTIVE_TAB: 'editor_bottom_active_tab',
	RESPONSE_BOTTOM_COLLAPSED: 'response_bottom_collapsed',
	RESPONSE_BOTTOM_ACTIVE_TAB: 'response_bottom_active_tab',
	SETTINGS_ACTIVE_SECTION: 'settings_active_section',

	// User-scoped, identity-bound.
	PERSONAL_COLLECTIONS: 'personal_collections',
	COLLECTION_SORT_MODES: 'collection_sort_modes',
	COLLECTION_ORDER: 'collection_order',
	SEEN_SHARED_COLLECTIONS: 'seen_shared_collections',
	COLLAPSED_NOTICES: 'collapsed_notices',
	SECTION_STATES: 'section_states',
});

// Mutable runtime registry — seeded with the built-ins, extended by
// plugins. Using a Map so iteration order is insertion order and
// `Object.freeze` semantics on `BUILT_IN_SCOPES` stay intact.
const scopeRegistry = new Map(Object.entries(BUILT_IN_SCOPES));

/**
 * Register a preference key so {@link setPreference}, {@link getPreference},
 * and {@link readDevicePreference} know where it lives. Safe to call any
 * time; re-registering the same key with a different scope overwrites the
 * previous mapping (intended — lets a host change its mind without a
 * page reload).
 *
 * @since x-release-please-version
 *
 * **Key format.** `device`-scope keys can be any non-empty string —
 * they live in localStorage and aren't constrained. `user`-scope keys
 * are serialized into WordPress user-meta and must match the WP meta
 * key format: start with `[A-Za-z_]` and contain only `[A-Za-z0-9_]`.
 * Recommended convention for both is `my_plugin_setting_name` so a
 * pref can move between scopes without renaming.
 *
 * @param {string}            key          Preference key. Plugins should
 *                                         prefix with a short plugin
 *                                         identifier (`my_plugin_*`) to
 *                                         avoid colliding with built-ins
 *                                         or other extensions.
 * @param {Object}            config       Registration config.
 * @param {'device' | 'user'} config.scope
 *                                         `'device'` — localStorage on this browser/render-context. Cheap,
 *                                         works for anonymous endpoint visitors. Good for UI chrome.
 *                                         `'user'` — server user-meta via REST. Logged-in only, cross-device.
 *                                         Reserved for identity-bound data.
 *
 * @return {void}
 */
export function registerPreference(key, config) {
	if (typeof key !== 'string' || key === '') {
		// eslint-disable-next-line no-console
		console.error(
			'registerPreference: a non-empty string key is required.'
		);
		return;
	}
	const scope = config?.scope;
	if (scope !== 'device' && scope !== 'user') {
		// eslint-disable-next-line no-console
		console.error(
			`registerPreference: "${key}" requires { scope: 'device' | 'user' }; got ${JSON.stringify(scope)}.`
		);
		return;
	}
	scopeRegistry.set(key, scope);
}

/**
 * @param {string} key
 * @return {'device' | 'user'}
 */
export function scopeOf(key) {
	return scopeRegistry.get(key) || 'user';
}

/**
 * Whether a key has been explicitly registered (via the built-in seed
 * or {@link registerPreference}). Useful for "did I typo this?" checks
 * in dev builds. Unregistered keys still work — they fall through to
 * the `'user'` default in {@link scopeOf} — but production code should
 * register every key it uses.
 *
 * @param {string} key
 * @return {boolean}
 */
export function isPreferenceRegistered(key) {
	return scopeRegistry.has(key);
}

function deviceStorageKey() {
	const data =
		typeof window !== 'undefined' ? window.WPGRAPHQL_IDE_DATA : null;
	const id = (data && Number(data?.context?.currentUserId)) || 0;
	const ctx = data?.endpointMode ? 'endpoint' : 'admin';
	return `wpgraphql-ide:prefs:${STORAGE_VERSION}:user-${id}:ctx-${ctx}`;
}

/**
 * Whether the current visitor is logged in. `user`-scope preferences
 * round-trip through `/wp/v2/users/me` to set user meta and require an
 * authenticated request; this is the gate for those code paths. Read
 * inline so a late `WPGRAPHQL_IDE_DATA` injection (auth state changing
 * mid-session via the public-endpoint sign-in flow) is picked up
 * without needing a module reload.
 *
 * @return {boolean}
 */
function isLoggedIn() {
	return (
		typeof window !== 'undefined' &&
		!!window.WPGRAPHQL_IDE_DATA?.isUserLoggedIn
	);
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
	// Anonymous visitors have no user meta to read. Skip the round-trip
	// so the public endpoint doesn't generate a 401/403 on every page
	// load (was silently swallowed, but still showed in the browser's
	// network panel and console for anyone with devtools open).
	if (!isLoggedIn()) {
		return {};
	}
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
	} catch (error) {
		// Logged in but the read failed — likely a transient REST or
		// network error. Warn rather than swallow so debugging a missing
		// preference doesn't start with "where did my pref go?".
		// eslint-disable-next-line no-console
		console.warn('Failed to read IDE preferences from server:', error);
		return {};
	}
}

async function writeServerPref(key, value) {
	if (!isLoggedIn()) {
		// Fast-fail before the apiFetch round-trip with a message that
		// explains the actual cause — a `user`-scope write on an
		// anonymous visitor always 403s, and the previous behavior
		// dumped the raw 403 to the console with no clue that the fix
		// is `registerPreference(..., { scope: 'device' })`.
		throw new Error(
			`Cannot write user-scoped preference "${key}": no logged-in user. ` +
				"Register the preference with { scope: 'device' } if it should persist for anonymous visitors."
		);
	}
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
