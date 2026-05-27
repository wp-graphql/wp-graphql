import {
	scopeOf,
	readDevicePreference,
	getPreference,
	setPreference,
	getPreferences,
	registerPreference,
	isPreferenceRegistered,
	PREFERENCE_KEYS,
} from '../../../../src/api/preferences';

jest.mock('@wordpress/api-fetch');
import apiFetch from '@wordpress/api-fetch';

describe('preferences adapter', () => {
	beforeEach(() => {
		window.localStorage.clear();
		// Default to a logged-in admin so the server-backed paths are
		// exercised. Tests covering anonymous / public-endpoint behavior
		// override `isUserLoggedIn` inside their own describe block.
		window.WPGRAPHQL_IDE_DATA = {
			context: { currentUserId: 0 },
			endpointMode: false,
			isUserLoggedIn: true,
		};
		apiFetch.mockReset();
	});

	afterEach(() => {
		delete window.WPGRAPHQL_IDE_DATA;
	});

	describe('scopeOf', () => {
		it('returns "device" for UI chrome keys', () => {
			expect(scopeOf('response_view_mode')).toBe('device');
			expect(scopeOf('panel_order')).toBe('device');
			expect(scopeOf('left_panel')).toBe('device');
			expect(scopeOf('open_tabs')).toBe('device');
			expect(scopeOf('active_tab')).toBe('device');
			expect(scopeOf('visible_panel')).toBe('device');
		});

		it('returns "user" for identity-bound keys', () => {
			expect(scopeOf('personal_collections')).toBe('user');
			expect(scopeOf('collection_sort_modes')).toBe('user');
			expect(scopeOf('seen_shared_collections')).toBe('user');
		});

		it('falls back to "user" for unknown keys', () => {
			expect(scopeOf('unrecognized_key')).toBe('user');
		});
	});

	describe('readDevicePreference', () => {
		it('returns undefined when nothing is stored', () => {
			expect(readDevicePreference('left_panel')).toBeUndefined();
		});

		it('returns undefined for user-scope keys (sync path is device-only)', () => {
			expect(
				readDevicePreference('personal_collections')
			).toBeUndefined();
		});

		it('reads from the per-user-and-context bucket', () => {
			window.localStorage.setItem(
				'wpgraphql-ide:prefs:v1:user-0:ctx-admin',
				JSON.stringify({ left_panel: 'composer' })
			);
			expect(readDevicePreference('left_panel')).toBe('composer');
		});

		it('partitions by user id', () => {
			window.localStorage.setItem(
				'wpgraphql-ide:prefs:v1:user-7:ctx-admin',
				JSON.stringify({ left_panel: 'settings' })
			);
			window.WPGRAPHQL_IDE_DATA.context.currentUserId = 0;
			expect(readDevicePreference('left_panel')).toBeUndefined();
			window.WPGRAPHQL_IDE_DATA.context.currentUserId = 7;
			expect(readDevicePreference('left_panel')).toBe('settings');
		});

		it('partitions admin and endpoint contexts', () => {
			window.localStorage.setItem(
				'wpgraphql-ide:prefs:v1:user-0:ctx-admin',
				JSON.stringify({ left_panel: 'composer' })
			);
			window.WPGRAPHQL_IDE_DATA.endpointMode = false;
			expect(readDevicePreference('left_panel')).toBe('composer');
			window.WPGRAPHQL_IDE_DATA.endpointMode = true;
			expect(readDevicePreference('left_panel')).toBeUndefined();
		});
	});

	describe('setPreference', () => {
		it('writes device-scope keys to localStorage', async () => {
			await setPreference('left_panel', 'composer');
			expect(readDevicePreference('left_panel')).toBe('composer');
			expect(apiFetch).not.toHaveBeenCalled();
		});

		it('routes user-scope keys to the REST endpoint', async () => {
			apiFetch.mockResolvedValueOnce({});
			await setPreference('personal_collections', [{ id: 'pc_1' }]);
			expect(apiFetch).toHaveBeenCalledWith({
				path: '/wp/v2/users/me',
				method: 'POST',
				data: {
					meta: {
						wpgraphql_ide_personal_collections: [{ id: 'pc_1' }],
					},
				},
			});
		});

		it('overwrites a prior device-scope value', async () => {
			await setPreference('left_panel', 'composer');
			await setPreference('left_panel', 'settings');
			expect(readDevicePreference('left_panel')).toBe('settings');
		});

		it('preserves other keys in the bucket on a single-key write', async () => {
			await setPreference('left_panel', 'composer');
			await setPreference('response_view_mode', 'table');
			expect(readDevicePreference('left_panel')).toBe('composer');
			expect(readDevicePreference('response_view_mode')).toBe('table');
		});
	});

	describe('getPreference', () => {
		it('reads device-scope values from localStorage', async () => {
			await setPreference('visible_panel', 'docs-explorer');
			await expect(getPreference('visible_panel')).resolves.toBe(
				'docs-explorer'
			);
			expect(apiFetch).not.toHaveBeenCalled();
		});

		it('reads user-scope values from the REST endpoint', async () => {
			apiFetch.mockResolvedValueOnce({
				meta: {
					wpgraphql_ide_personal_collections: [{ id: 'pc_x' }],
				},
			});
			await expect(
				getPreference('personal_collections')
			).resolves.toEqual([{ id: 'pc_x' }]);
		});
	});

	describe('getPreferences', () => {
		it('merges server meta and the device bucket', async () => {
			await setPreference('left_panel', 'composer');
			apiFetch.mockResolvedValueOnce({
				meta: {
					wpgraphql_ide_personal_collections: [{ id: 'pc_x' }],
				},
			});
			const merged = await getPreferences();
			expect(merged.left_panel).toBe('composer');
			expect(merged.personal_collections).toEqual([{ id: 'pc_x' }]);
		});

		it('lets device values override stale server values for migrated keys', async () => {
			await setPreference('left_panel', 'composer');
			apiFetch.mockResolvedValueOnce({
				meta: {
					// Simulating a stale server value left over from before
					// `left_panel` was demoted to `device` scope.
					wpgraphql_ide_left_panel: 'settings',
				},
			});
			const merged = await getPreferences();
			expect(merged.left_panel).toBe('composer');
		});

		it('returns an empty merged object when both sources are empty', async () => {
			apiFetch.mockResolvedValueOnce({ meta: {} });
			await expect(getPreferences()).resolves.toEqual({});
		});

		it('survives a server fetch failure by returning the device bucket alone', async () => {
			const warnSpy = jest
				.spyOn(console, 'warn')
				.mockImplementation(() => {});
			await setPreference('left_panel', 'composer');
			apiFetch.mockRejectedValueOnce(new Error('401'));
			const merged = await getPreferences();
			expect(merged.left_panel).toBe('composer');
			// The failure path warns rather than silently swallowing —
			// callers (and devs) shouldn't hunt for "where did my pref go".
			expect(warnSpy).toHaveBeenCalledWith(
				'Failed to read IDE preferences from server:',
				expect.any(Error)
			);
			warnSpy.mockRestore();
		});
	});

	describe('localStorage failure handling', () => {
		it('treats malformed JSON in the bucket as an empty bucket', () => {
			window.localStorage.setItem(
				'wpgraphql-ide:prefs:v1:user-0:ctx-admin',
				'not-json'
			);
			expect(readDevicePreference('left_panel')).toBeUndefined();
		});
	});

	describe('registerPreference / extension surface', () => {
		// The runtime scope registry is module-level state — tests below
		// register namespaced plugin keys that won't collide with built-ins
		// or each other. (The registry is monotonic: once a key is
		// registered for a scope it stays in the map for the rest of the
		// test process. Tests assert behavior, not identity.)

		it('exposes PREFERENCE_KEYS constants for every built-in', () => {
			// Sanity: the constants object covers the 17 built-in keys
			// that drive UI chrome + user-meta state. If a built-in is
			// renamed, this test reminds us to update the constant.
			expect(PREFERENCE_KEYS.RESPONSE_VIEW_MODE).toBe(
				'response_view_mode'
			);
			expect(PREFERENCE_KEYS.PERSONAL_COLLECTIONS).toBe(
				'personal_collections'
			);
			expect(PREFERENCE_KEYS.OPEN_TABS).toBe('open_tabs');
			expect(Object.isFrozen(PREFERENCE_KEYS)).toBe(true);
		});

		it('treats built-ins as registered', () => {
			expect(isPreferenceRegistered('response_view_mode')).toBe(true);
			expect(isPreferenceRegistered('personal_collections')).toBe(true);
		});

		it('treats unknown keys as unregistered', () => {
			expect(isPreferenceRegistered('totally-not-a-real-key')).toBe(
				false
			);
		});

		it('lets plugins register a device-scoped key', () => {
			registerPreference('test-plugin/device-key', {
				scope: 'device',
			});
			expect(scopeOf('test-plugin/device-key')).toBe('device');
			expect(isPreferenceRegistered('test-plugin/device-key')).toBe(true);
		});

		it('lets plugins register a user-scoped key', () => {
			registerPreference('test-plugin/user-key', { scope: 'user' });
			expect(scopeOf('test-plugin/user-key')).toBe('user');
		});

		it('routes a registered device pref to localStorage', async () => {
			registerPreference('test-plugin/device-routed', {
				scope: 'device',
			});

			await setPreference('test-plugin/device-routed', 42);

			// Server adapter should not be called for device-scoped keys.
			expect(apiFetch).not.toHaveBeenCalled();
			expect(readDevicePreference('test-plugin/device-routed')).toBe(42);
		});

		it('routes a registered user pref to the server', async () => {
			// User-scope keys hit WP user-meta, which has a tighter
			// key format than localStorage. Stick to [a-z0-9_] for
			// the bare key portion — see the registerPreference docblock.
			registerPreference('test_plugin_user_routed', {
				scope: 'user',
			});
			apiFetch.mockResolvedValueOnce({});

			await setPreference('test_plugin_user_routed', 'hello');

			expect(apiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/wp/v2/users/me',
					method: 'POST',
					data: {
						meta: {
							wpgraphql_ide_test_plugin_user_routed: 'hello',
						},
					},
				})
			);
		});

		it('lets a host re-register an existing key with a new scope', () => {
			registerPreference('test-plugin/mutable', { scope: 'device' });
			expect(scopeOf('test-plugin/mutable')).toBe('device');

			registerPreference('test-plugin/mutable', { scope: 'user' });
			expect(scopeOf('test-plugin/mutable')).toBe('user');
		});

		it('logs and ignores invalid scope values', () => {
			const errorSpy = jest
				.spyOn(console, 'error')
				.mockImplementation(() => {});

			registerPreference('test-plugin/bad-scope', {
				scope: 'cloud',
			});

			expect(errorSpy).toHaveBeenCalled();
			expect(isPreferenceRegistered('test-plugin/bad-scope')).toBe(false);
			errorSpy.mockRestore();
		});

		it('logs and ignores empty / non-string keys', () => {
			const errorSpy = jest
				.spyOn(console, 'error')
				.mockImplementation(() => {});

			registerPreference('', { scope: 'device' });
			registerPreference(null, { scope: 'device' });
			registerPreference(42, { scope: 'device' });

			expect(errorSpy.mock.calls.length).toBeGreaterThanOrEqual(3);
			errorSpy.mockRestore();
		});
	});

	describe('anonymous visitor (public endpoint, not logged in)', () => {
		beforeEach(() => {
			window.WPGRAPHQL_IDE_DATA = {
				context: { currentUserId: 0 },
				endpointMode: true,
				isUserLoggedIn: false,
			};
		});

		it('readServerPrefs short-circuits without an apiFetch call', async () => {
			const merged = await getPreferences();
			expect(merged).toEqual({});
			expect(apiFetch).not.toHaveBeenCalled();
		});

		it('still merges the device bucket when server reads are skipped', async () => {
			await setPreference('open_tabs', ['temp-1']);
			const merged = await getPreferences();
			expect(merged.open_tabs).toEqual(['temp-1']);
			expect(apiFetch).not.toHaveBeenCalled();
		});

		it('device-scope writes work without a server round-trip', async () => {
			await setPreference('left_panel', 'composer');
			expect(readDevicePreference('left_panel')).toBe('composer');
			expect(apiFetch).not.toHaveBeenCalled();
		});

		it('user-scope writes throw with a clear, actionable message', async () => {
			// Built-in `personal_collections` is user-scope.
			await expect(
				setPreference('personal_collections', [{ id: 'pc_1' }])
			).rejects.toThrow(/no logged-in user/);
			await expect(
				setPreference('personal_collections', [])
			).rejects.toThrow(/scope: 'device'/);
			expect(apiFetch).not.toHaveBeenCalled();
		});

		it('unknown keys default to user-scope and therefore fast-fail', async () => {
			await expect(
				setPreference('plugin_unregistered_key', 'x')
			).rejects.toThrow(/no logged-in user/);
			expect(apiFetch).not.toHaveBeenCalled();
		});

		it('user-scope reads return undefined silently without a server hit', async () => {
			await expect(
				getPreference('personal_collections')
			).resolves.toBeUndefined();
			expect(apiFetch).not.toHaveBeenCalled();
		});
	});
});
