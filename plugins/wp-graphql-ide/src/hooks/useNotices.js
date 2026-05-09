import { useCallback, useEffect, useState } from 'react';
import hooks from '../wordpress-hooks';

/**
 * Snackbar-style notice state with a hook bus listener so extensions can
 * raise notices via `wpgraphql-ide.notice` without prop-drilling
 * `addNotice` everywhere.
 *
 * The first argument can be a plain string (back-compat — most callers)
 * or a structured payload `{ content, actions }` where `actions` is the
 * SnackbarList shape from `@wordpress/components`: `[{ label, onClick }]`.
 * Actions render as link-style buttons inside the snackbar, perfect for
 * "insert this snippet" or "open the Docs panel" affordances.
 *
 * The optional `type` arg lets callers raise `error` / `warning` notices
 * that get a styling hook in the renderer; default passes through.
 *
 * @return {{ notices: Array, addNotice: Function, removeNotice: Function }}
 */
export function useNotices() {
	const [notices, setNotices] = useState([]);

	const addNotice = useCallback((payload, type = 'default') => {
		const isObject =
			payload && typeof payload === 'object' && !Array.isArray(payload);
		const content = isObject ? payload.content : payload;
		const actions = isObject ? payload.actions : undefined;
		const id =
			(isObject && payload.id) ||
			`notice-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
		// Dedupe by id — a caller passing a stable id (e.g. mash-easter-egg
		// firings) gets a "replace previous" semantic instead of stacking
		// duplicates. Implicit ids are unique per call so back-compat holds.
		setNotices((prev) => {
			const next = prev.filter((n) => n.id !== id);
			return [...next, { id, content, type, actions }];
		});
	}, []);

	const removeNotice = useCallback((id) => {
		setNotices((prev) => prev.filter((n) => n.id !== id));
	}, []);

	useEffect(() => {
		const ns = 'wpgraphql-ide/layout';
		const noticeHandler = (payload, type = 'default') =>
			addNotice(payload, type);
		const dismissHandler = (id) => removeNotice(id);
		hooks.addAction('wpgraphql-ide.notice', ns, noticeHandler);
		hooks.addAction('wpgraphql-ide.notice.dismiss', ns, dismissHandler);
		return () => {
			hooks.removeAction('wpgraphql-ide.notice', ns);
			hooks.removeAction('wpgraphql-ide.notice.dismiss', ns);
		};
	}, [addNotice, removeNotice]);

	return { notices, addNotice, removeNotice };
}
