import React from 'react';
import { ResponseViewer } from '../editors/ResponseViewer';
import { ResponseTableView } from '../ResponseTableView';

/* eslint-disable jsdoc/require-param, jsdoc/require-param-type, jsdoc/require-param-description, jsdoc/check-param-names */

/**
 * Default JSON viewer — pretty-printed, syntax highlighted, scoped by
 * the data-scope toggle (data-only vs full envelope).
 */
export function FormattedViewMode({ viewerContent }) {
	return <ResponseViewer value={viewerContent} />;
}

/**
 * Tabular viewer — flattens edges/nodes into a sortable table. Honors
 * the data-scope toggle the same way.
 */
export function TableViewMode({ parsed, dataScope }) {
	return (
		<ResponseTableView
			response={dataScope === 'data' ? parsed?.data : parsed}
		/>
	);
}
