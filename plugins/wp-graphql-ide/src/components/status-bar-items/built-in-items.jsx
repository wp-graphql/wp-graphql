import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { detectNPlusOne } from '../response-extensions/detect-n-plus-one';

function formatDuration(ms) {
	if (ms === null) {
		return null;
	}
	if (ms >= 1000) {
		return sprintf(
			/* translators: %s: pre-formatted seconds value, e.g. "1.2" */
			__('%ss', 'wpgraphql-ide'),
			(ms / 1000).toFixed(1)
		);
	}
	return sprintf(
		/* translators: %d: milliseconds value */
		__('%dms', 'wpgraphql-ide'),
		ms
	);
}

function formatSize(bytes) {
	if (bytes === null) {
		return null;
	}
	if (bytes >= 1024) {
		return sprintf(
			/* translators: %s: pre-formatted kilobyte value, e.g. "1.2" */
			__('%sKB', 'wpgraphql-ide'),
			(bytes / 1024).toFixed(1)
		);
	}
	return sprintf(
		/* translators: %d: response size in bytes */
		__('%dB', 'wpgraphql-ide'),
		bytes
	);
}

/* eslint-disable jsdoc/require-param, jsdoc/require-param-type, jsdoc/require-param-description, jsdoc/check-param-names */

/**
 * HTTP status code (200 / 4xx / 5xx). Colored success or error.
 */
export function StatusCodeItem({ responseStatus }) {
	if (responseStatus === null || responseStatus === undefined) {
		return null;
	}
	const tone =
		responseStatus >= 200 && responseStatus < 300 ? 'success' : 'error';
	return (
		<span
			className={`wpgraphql-ide-response-status wpgraphql-ide-response-status--${tone}`}
		>
			{responseStatus}
		</span>
	);
}

/**
 * Wall-clock fetch-to-response duration.
 */
export function DurationItem({ responseDuration }) {
	if (responseDuration === null || responseDuration === undefined) {
		return null;
	}
	return (
		<span className="wpgraphql-ide-response-duration">
			{formatDuration(responseDuration)}
		</span>
	);
}

/**
 * Response payload size in bytes.
 */
export function SizeItem({ responseSize }) {
	if (responseSize === null || responseSize === undefined) {
		return null;
	}
	return (
		<span className="wpgraphql-ide-response-size">
			{formatSize(responseSize)}
		</span>
	);
}

/**
 * Resolver-count badge — clicks open the Tracing tab. Hidden when no
 * tracing data is present in the response.
 */
export function ResolverCountItem({ parsedResponse, focusResponseTab }) {
	const tracing = parsedResponse?.extensions?.tracing;
	const resolvers = Array.isArray(tracing?.execution?.resolvers)
		? tracing.execution.resolvers
		: null;
	if (!resolvers) {
		return null;
	}
	return (
		<button
			type="button"
			className="wpgraphql-ide-response-trace-badge"
			onClick={() => focusResponseTab('ext:tracing')}
			title={__('Open the Tracing tab', 'wpgraphql-ide')}
		>
			{sprintf(
				/* translators: %d: number of GraphQL resolvers that ran for this response */
				_n(
					'%d resolver',
					'%d resolvers',
					resolvers.length,
					'wpgraphql-ide'
				),
				resolvers.length
			)}
		</button>
	);
}

/**
 * N+1 warning badge — surfaces likely N+1 patterns from the resolver
 * trace. Hidden when none are detected.
 */
export function NPlusOneItem({ parsedResponse, focusResponseTab }) {
	const tracing = parsedResponse?.extensions?.tracing;
	const resolvers = Array.isArray(tracing?.execution?.resolvers)
		? tracing.execution.resolvers
		: null;
	if (!resolvers) {
		return null;
	}
	const patterns = detectNPlusOne(resolvers);
	if (patterns.length === 0) {
		return null;
	}
	return (
		<button
			type="button"
			className="wpgraphql-ide-response-trace-badge wpgraphql-ide-response-trace-badge--warning"
			onClick={() => focusResponseTab('ext:tracing')}
			title={__(
				'Open the Tracing tab to see the N+1 patterns',
				'wpgraphql-ide'
			)}
		>
			{sprintf(
				/* translators: %d: number of likely N+1 query patterns detected */
				__('⚠ %d N+1', 'wpgraphql-ide'),
				patterns.length
			)}
		</button>
	);
}
