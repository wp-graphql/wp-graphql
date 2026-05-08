import { useCallback, useState } from 'react';

const STORAGE_KEY = 'wpgraphql_ide_left_panel';
const LEGACY_KEY = 'wpgraphql_ide_show_query_composer';

/**
 * Read the persisted left-panel choice (Composer or Document Settings),
 * consuming the legacy `wpgraphql_ide_show_query_composer` flag during
 * the migration window.
 *
 * Closing the panel clears the unified key. If the legacy entry stayed
 * around it would silently override that close on the next refresh, so
 * we delete it on first read regardless of value, then promote a
 * truthy legacy value into a Composer-open default only if the unified
 * key is empty.
 */
function readInitialPanel() {
	try {
		const legacy = window.localStorage.getItem(LEGACY_KEY);
		if (legacy !== null) {
			window.localStorage.removeItem(LEGACY_KEY);
			if (
				legacy === 'true' &&
				!window.localStorage.getItem(STORAGE_KEY)
			) {
				window.localStorage.setItem(STORAGE_KEY, 'composer');
			}
		}
		const stored = window.localStorage.getItem(STORAGE_KEY);
		if (stored === 'composer' || stored === 'settings') {
			return stored;
		}
	} catch {
		// ignore
	}
	return null;
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
 * or the Document Settings panel. Persists the choice + each panel's
 * resizable width to localStorage.
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
export function useLeftPanel() {
	const [leftPanel, setLeftPanelState] = useState(readInitialPanel);

	const setLeftPanel = useCallback((next) => {
		setLeftPanelState(next);
		try {
			if (next === null) {
				window.localStorage.removeItem(STORAGE_KEY);
			} else {
				window.localStorage.setItem(STORAGE_KEY, next);
			}
		} catch {
			// ignore
		}
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
