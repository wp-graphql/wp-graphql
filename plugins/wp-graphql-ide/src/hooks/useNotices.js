import { useCallback, useEffect, useState } from 'react';
import hooks from '../wordpress-hooks';

/**
 * Snackbar-style notice state with a hook bus listener so extensions can
 * raise notices via `wpgraphql-ide.notice` without prop-drilling
 * `addNotice` everywhere.
 *
 * The optional `type` arg on the action lets callers raise `error` /
 * `warning` notices that get a styling hook in the renderer; default
 * passes through unchanged.
 *
 * @return {{ notices: Array, addNotice: Function, removeNotice: Function }}
 */
export function useNotices() {
	const [notices, setNotices] = useState([]);

	const addNotice = useCallback((content, type = 'default') => {
		const id = `notice-${Date.now()}`;
		setNotices((prev) => [...prev, { id, content, type }]);
	}, []);

	const removeNotice = useCallback((id) => {
		setNotices((prev) => prev.filter((n) => n.id !== id));
	}, []);

	useEffect(() => {
		const hookName = 'wpgraphql-ide.notice';
		const ns = 'wpgraphql-ide/layout';
		const handler = (content, type = 'default') => addNotice(content, type);
		hooks.addAction(hookName, ns, handler);
		return () => hooks.removeAction(hookName, ns);
	}, [addNotice]);

	return { notices, addNotice, removeNotice };
}
