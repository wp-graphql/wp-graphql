// Module-level registry for workspace-tab persistence (save/discard).
//
// Workspace tabs (Settings, etc.) own state that doesn't live in the
// document store, so the generic save/discard hooks in IDELayout need a way
// to delegate to whatever the tab itself uses to persist or wipe pending
// edits. Each tab type that wants to participate in the dirty flow
// registers a `{ save, discard }` pair keyed by its tabType name.
//
// `save` is called when the close-tab dialog's "Save and close" is picked
// or could be invoked from anywhere else that needs to save the active
// workspace tab. `discard` is called when the user chooses "Discard" so
// pending in-memory edits don't linger after the tab is closed.

const handlers = new Map();

export function registerWorkspacePersistence(tabType, { save, discard } = {}) {
	handlers.set(tabType, { save, discard });
	return () => {
		const current = handlers.get(tabType);
		if (current && current.save === save && current.discard === discard) {
			handlers.delete(tabType);
		}
	};
}

export function getWorkspacePersistence(tabType) {
	return handlers.get(tabType) || null;
}
