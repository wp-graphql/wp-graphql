import { useCallback, useState } from 'react';
import { readDevicePreference, setPreference } from '../api/preferences';

const LEGACY_LOCAL_KEY = 'wpgraphql_ide_left_panel';
const LEGACY_FLAG_KEY = 'wpgraphql_ide_show_query_composer';

/**
 * Read the initial panel choice. Resolution order:
 *
 *   1. New device-scope preference bucket — the canonical location
 *      since `left_panel` is now device-scoped.
 *   2. Server-injected `WPGRAPHQL_IDE_DATA.leftPanel` — kept as a
 *      transitional fallback while the bootstrap field still ships;
 *      removed when the server stops injecting it.
 *   3. Single-key legacy localStorage entries (`wpgraphql_ide_left_panel`
 *      and the older `show_query_composer` flag) — read once and
 *      cleared so they can't silently override a future close.
 *   4. In endpoint mode, default to opening the Query Composer —
 *      schema browsing is the primary use case for the public-endpoint
 *      render. The dedicated admin page keeps `null`.
 *
 * @param {boolean} [endpointMode]
 */
function readInitialPanel(endpointMode = false) {
	const fromDevice = readDevicePreference('left_panel');
	if (fromDevice === 'composer' || fromDevice === 'settings') {
		return fromDevice;
	}
	if (fromDevice === '') {
		return null;
	}

	const fromBootstrap =
		typeof window !== 'undefined' && window.WPGRAPHQL_IDE_DATA?.leftPanel;
	if (fromBootstrap === 'composer' || fromBootstrap === 'settings') {
		return fromBootstrap;
	}

	try {
		const stored = window.localStorage.getItem(LEGACY_LOCAL_KEY);
		const legacyFlag = window.localStorage.getItem(LEGACY_FLAG_KEY);
		let migrated = null;
		if (stored === 'composer' || stored === 'settings') {
			migrated = stored;
		} else if (legacyFlag === 'true') {
			migrated = 'composer';
		}
		if (stored !== null) {
			window.localStorage.removeItem(LEGACY_LOCAL_KEY);
		}
		if (legacyFlag !== null) {
			window.localStorage.removeItem(LEGACY_FLAG_KEY);
		}
		if (migrated) {
			setPreference('left_panel', migrated).catch(() => {
				// Best-effort. Device writes don't actually fail in a way
				// that returns a Promise rejection, but keep the catch
				// in case scopeOf flips back to user later.
			});
			return migrated;
		}
	} catch {
		// ignore
	}
	return endpointMode ? 'composer' : null;
}

function readPersistedWidth(key, fallback) {
	try {
		const w = parseInt(window.localStorage.getItem(key), 10);
		return w > 0 ? w : fallback;
	} catch {
		return fallback;
	}
}

function usePersistedWidth(key, fallback) {
	const [width, setWidth] = useState(() => readPersistedWidth(key, fallback));
	const setPersistedWidth = useCallback(
		(next) => {
			setWidth(next);
			try {
				window.localStorage.setItem(key, String(next));
			} catch {
				// ignore
			}
		},
		[key]
	);
	return [width, setPersistedWidth];
}

/**
 * Single-slot left panel state that mutually hosts the Query Composer
 * or the Document Settings panel. Persists the choice via user meta
 * (REST) and each panel's resizable width to localStorage.
 *
 * @param {Object}  [opts]
 * @param {boolean} [opts.endpointMode] When true and no preference is
 *                                      stored, default to the Query
 *                                      Composer being open.
 *
 * @return {{
 *   leftPanel: 'composer' | 'settings' | null,
 *   setLeftPanel: Function,
 *   showQueryComposer: boolean,
 *   showDocSettingsPanel: boolean,
 *   toggleQueryComposer: Function,
 *   toggleDocSettingsPanel: Function,
 *   composerWidth: number,
 *   setComposerWidth: Function,
 *   docSettingsPanelWidth: number,
 *   setDocSettingsPanelWidth: Function,
 * }}
 */
export function useLeftPanel({ endpointMode = false } = {}) {
	const [leftPanel, setLeftPanelState] = useState(() =>
		readInitialPanel(endpointMode)
	);

	const setLeftPanel = useCallback((next) => {
		setLeftPanelState(next);
		// Device-scoped — adapter writes to localStorage. Fire-and-forget
		// to keep the toggle synchronous for the user.
		setPreference('left_panel', next === null ? '' : next).catch(() => {
			// ignore
		});
	}, []);

	const toggleQueryComposer = useCallback(() => {
		setLeftPanel(leftPanel === 'composer' ? null : 'composer');
	}, [leftPanel, setLeftPanel]);

	const toggleDocSettingsPanel = useCallback(() => {
		setLeftPanel(leftPanel === 'settings' ? null : 'settings');
	}, [leftPanel, setLeftPanel]);

	const [composerWidth, setComposerWidth] = usePersistedWidth(
		'wpgraphql_ide_composer_width',
		280
	);
	const [docSettingsPanelWidth, setDocSettingsPanelWidth] = usePersistedWidth(
		'wpgraphql_ide_settings_panel_width',
		360
	);

	return {
		leftPanel,
		setLeftPanel,
		showQueryComposer: leftPanel === 'composer',
		showDocSettingsPanel: leftPanel === 'settings',
		toggleQueryComposer,
		toggleDocSettingsPanel,
		composerWidth,
		setComposerWidth,
		docSettingsPanelWidth,
		setDocSettingsPanelWidth,
	};
}
