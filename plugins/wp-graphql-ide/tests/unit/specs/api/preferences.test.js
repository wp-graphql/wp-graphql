import {
	scopeOf,
	readDevicePreference,
	getPreference,
	setPreference,
	getPreferences,
} from '../../../../src/api/preferences';

jest.mock('@wordpress/api-fetch');
import apiFetch from '@wordpress/api-fetch';

describe('preferences adapter', () => {
	beforeEach(() => {
		window.localStorage.clear();
		window.WPGRAPHQL_IDE_DATA = {
			context: { currentUserId: 0 },
			endpointMode: false,
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
			expect(readDevicePreference('personal_collections')).toBeUndefined();
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
			await expect(getPreference('personal_collections')).resolves.toEqual([
				{ id: 'pc_x' },
			]);
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
			await setPreference('left_panel', 'composer');
			apiFetch.mockRejectedValueOnce(new Error('401'));
			const merged = await getPreferences();
			expect(merged.left_panel).toBe('composer');
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
});
