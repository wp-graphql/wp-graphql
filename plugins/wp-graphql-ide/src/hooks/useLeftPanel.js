import { useCallback, useState } from 'react';
import { savePreference } from '../api/preferences';

const LEGACY_LOCAL_KEY = 'wpgraphql_ide_left_panel';
const LEGACY_FLAG_KEY = 'wpgraphql_ide_show_query_composer';

/**
 * Read the initial panel choice. Resolution order:
 *
 *   1. Server-injected user meta (`WPGRAPHQL_IDE_DATA.leftPanel`) wins
 *      if set — that's the durable, per-user, cross-browser value.
 *   2. Otherwise, accept the older `wpgraphql_ide_left_panel`
 *      localStorage value and, on the same load, promote it to user
 *      meta so future paints read directly from the bootstrap.
 *   3. Otherwise, fall back to the even-older `show_query_composer`
 *      flag (also localStorage) and promote it the same way.
 *   4. Otherwise, in endpoint mode, default to opening the Query
 *      Composer — schema browsing is the primary use case for the
 *      public-endpoint render. The dedicated admin page keeps `null`
 *      so existing users don't see the composer pop open on next visit.
 *
 * Both legacy localStorage keys are deleted on first read so they
 * can't silently override a future close. The user-meta write is
 * fire-and-forget; failure leaves the localStorage hint in place,
 * which means the migration retries on the next page load.
 *
 * @param {boolean} [endpointMode]
 */
function readInitialPanel(endpointMode = false) {
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
			savePreference('left_panel', migrated).catch(() => {
				// Best-effort. If the write fails the migration retries
				// next load (legacy keys are already cleared, so it'll
				// quietly fall through to the endpoint-mode default).
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
		// Persist per-user via REST. Fire-and-forget; failures fall back
		// to the in-memory state for the rest of the session and
		// re-attempt next time the user toggles.
		savePreference('left_panel', next === null ? '' : next).catch(() => {
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
