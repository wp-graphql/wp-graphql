import React from 'react';
import { HeadersPanel } from '../HeadersPanel';

/**
 * Response-extension tab wrapper for HeadersPanel. Receives the response
 * headers map as `data` from the registry's synthetic `headers` slot.
 *
 * @param {Object}      props
 * @param {Object|null} props.data Headers map keyed by lowercase header name.
 *
 * @return {JSX.Element}
 */
export function HeadersExtensionTab({ data }) {
	return <HeadersPanel headers={data} />;
}
