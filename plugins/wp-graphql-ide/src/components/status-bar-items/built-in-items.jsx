import React from 'react';
import { detectNPlusOne } from '../response-extensions/detect-n-plus-one';

function formatDuration(ms) {
	if (ms === null) {
		return null;
	}
	return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

function formatSize(bytes) {
	if (bytes === null) {
		return null;
	}
	return bytes >= 1024 ? `${(bytes / 1024).toFixed(1)}KB` : `${bytes}B`;
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
			title="Open the Tracing tab"
		>
			{resolvers.length} resolver{resolvers.length === 1 ? '' : 's'}
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
			title="Open the Tracing tab to see the N+1 patterns"
		>
			⚠ {patterns.length} N+1
		</button>
	);
}
