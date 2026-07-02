import { createSelector } from '@wordpress/data';
import { isTempId } from '../../utils/document-id';

const selectors = {
	buttons: createSelector(
		(state) => {
			const buttons = Object.entries(state.buttons).map(
				([name, button]) => ({
					name,
					...button,
				})
			);

			return buttons.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.buttons]
	),

	getDocuments: createSelector(
		(state) =>
			(state.documentIds || [])
				.map((id) => state.documents[id])
				.filter(Boolean),
		(state) => [state.documents, state.documentIds]
	),

	// Documents minus workspace tabs (Settings, etc. — they live in
	// the same store so the tab strip can render their titles, but
	// they're not query documents). Memoized so consumers
	// (SavedQueriesPanel) don't trigger wp-data's "non-equal value"
	// warning by .filter()ing a stable array into a fresh reference
	// on every render.
	getQueryDocuments: createSelector(
		(state) =>
			(state.documentIds || [])
				.map((id) => state.documents[id])
				.filter((doc) => doc && !doc.tabType),
		(state) => [state.documents, state.documentIds]
	),

	getDocumentResponse: (state, id) => {
		if (id === null || id === undefined) {
			return '';
		}
		return state.documentResponses?.[String(id)] || '';
	},

	// Memoized: returns the same array reference when `state.openTabs`
	// is unchanged. Without this, `useSelect` consumers see a fresh
	// `.map(...)` array on every dispatch and trigger a wp-data
	// "non-equal value" warning + needless re-renders.
	getOpenTabs: createSelector(
		(state) => state.openTabs.map((tab) => tab.id),
		(state) => [state.openTabs]
	),

	getOpenTabObjects: (state) => state.openTabs,

	getActiveTab: (state) => state.activeTab,

	getActiveTabType: (state) =>
		state.openTabs.find((tab) => tab.id === state.activeTab)?.type || null,

	getActiveDocument: (state) => {
		if (!state.activeTab) {
			return null;
		}
		return state.documents[state.activeTab] || null;
	},

	getTabTypes: (state) => state.tabTypes,

	getTabType: (state, name) => state.tabTypes[name] || null,

	getTopbarActions: createSelector(
		(state) => {
			return Object.entries(state.topbarActions)
				.map(([name, action]) => ({ name, ...action }))
				.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.topbarActions]
	),

	// State-free helper exposed as a selector so internal components and
	// third parties have one place to ask "is this an unsaved tab?" Tabs
	// the editor created in-memory carry a `temp-…` ID prefix until
	// `saveTab` swaps it for the real CPT post id. Implementation lives
	// in `utils/document-id.js` so the predicate isn't redefined in
	// three places.
	isTempId: (state, id) => isTempId(id),
};

export default selectors;
