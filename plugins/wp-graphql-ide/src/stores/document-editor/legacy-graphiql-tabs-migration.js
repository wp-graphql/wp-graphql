/**
 * One-shot migration of 4.x GraphiQL tab state into the 5.0 storage
 * model.
 *
 * 4.x stored open tabs under the GraphiQL default `graphiql:tabState`
 * key. 5.0 stores them as:
 *   - unsaved tab payloads in the `wpgraphql-ide:unsaved-tabs:v1:...`
 *     bucket (one entry per draft, addressed by a `temp-` id), and
 *   - an `open_tabs` array of ids in the device preferences bucket
 *     (`wpgraphql-ide:prefs:v1:...`), with `active_tab` recording the
 *     focused id.
 *
 * This migrator reads the legacy key, mints stable `temp-` ids for the
 * carried tabs, writes them through the existing unsaved-tabs/prefs
 * adapters, then deletes the legacy key. A separate flag key records
 * that migration ran so repeat calls (rehydrate, second IDE mount) are
 * no-ops.
 *
 * Errors are swallowed and the flag is set anyway: a partial / skipped
 * migration is better than failing the IDE boot or retrying forever on
 * malformed legacy data. The worst-case user impact is an empty tab
 * strip — recoverable by simply opening a new tab.
 *
 * @since x-release-please-version
 */

import { saveUnsavedTab } from './unsaved-tabs-storage';
import { setPreference } from '../../api/preferences';

const LEGACY_TAB_STATE_KEY = 'graphiql:tabState';
const MIGRATION_FLAG_KEY = 'wpgraphql-ide:graphiql-tabstate-migrated:v1';

function hasLocalStorage() {
	return typeof window !== 'undefined' && !!window.localStorage;
}

function markComplete() {
	try {
		window.localStorage.setItem(MIGRATION_FLAG_KEY, '1');
	} catch {
		// Out of quota or storage disabled — nothing we can do.
	}
}

/**
 * Run the migration if it hasn't already run. Returns a small descriptor
 * so callers can log / notice / surface UI based on the outcome.
 *
 * @return {Promise<{ migrated: boolean, tabCount?: number, activeTabId?: string }>}
 */
export async function migrateLegacyTabs() {
	if (!hasLocalStorage()) {
		return { migrated: false };
	}

	try {
		if (window.localStorage.getItem(MIGRATION_FLAG_KEY)) {
			return { migrated: false };
		}

		const raw = window.localStorage.getItem(LEGACY_TAB_STATE_KEY);
		if (!raw) {
			markComplete();
			return { migrated: false };
		}

		let parsed;
		try {
			parsed = JSON.parse(raw);
		} catch {
			// Malformed legacy payload — drop it so we don't keep retrying.
			window.localStorage.removeItem(LEGACY_TAB_STATE_KEY);
			markComplete();
			return { migrated: false };
		}

		const tabs = Array.isArray(parsed?.tabs) ? parsed.tabs : [];
		if (tabs.length === 0) {
			window.localStorage.removeItem(LEGACY_TAB_STATE_KEY);
			markComplete();
			return { migrated: false };
		}

		const activeIndex = Number.isInteger(parsed?.activeTabIndex)
			? parsed.activeTabIndex
			: 0;

		const now = Date.now();
		const newIds = [];

		tabs.forEach((tab, i) => {
			const id = `temp-${now}-${i}`;
			saveUnsavedTab({
				id,
				title:
					typeof tab?.title === 'string' && tab.title.trim()
						? tab.title
						: `Migrated tab ${i + 1}`,
				query: typeof tab?.query === 'string' ? tab.query : '',
				variables:
					typeof tab?.variables === 'string' ? tab.variables : '',
				headers: typeof tab?.headers === 'string' ? tab.headers : '',
			});
			newIds.push(id);
		});

		const activeId =
			activeIndex >= 0 && activeIndex < newIds.length
				? newIds[activeIndex]
				: newIds[0];

		await Promise.all([
			setPreference('open_tabs', newIds),
			setPreference('active_tab', activeId),
		]);

		window.localStorage.removeItem(LEGACY_TAB_STATE_KEY);
		markComplete();

		return {
			migrated: true,
			tabCount: newIds.length,
			activeTabId: activeId,
		};
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn('Legacy GraphiQL tab migration failed:', error);
		markComplete();
		return { migrated: false };
	}
}
