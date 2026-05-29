// Platform-aware labels for keyboard shortcuts shown in UI text
// (tooltips, status tips, the welcome buffer). Resolved once at module
// load — the platform doesn't change inside a session.
const ua =
	typeof window !== 'undefined' && window.navigator
		? window.navigator.platform || window.navigator.userAgent || ''
		: '';

export const isMac = /Mac|iPhone|iPad/.test(ua);

export const MOD_LABEL = isMac ? 'Cmd' : 'Ctrl';
export const RUN_QUERY_LABEL = `${MOD_LABEL}+Enter`;
export const SAVE_LABEL = `${MOD_LABEL}+S`;
export const PRETTIFY_LABEL = `${MOD_LABEL}+Shift+P`;
export const MERGE_LABEL = `${MOD_LABEL}+Shift+M`;
