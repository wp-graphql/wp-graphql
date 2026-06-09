/**
 * Content-addressed identity for a GraphQL operation.
 *
 * sha256( parse → print( queryString ) ) matches Smart Cache's own
 * normalization (see plugins/wp-graphql-smart-cache/src/Document.php's
 * `save_document_cb` and `Utils::generateHash`). Two queries that differ
 * only in whitespace, comments, or insignificant trivia produce the same
 * hash — which is what we need to let the IDE's operation history line
 * up 1:1 with published `graphql_document` slugs.
 *
 * Returns `null` for inputs that can't be parsed; callers should treat
 * those as ungrouped (each execution stands alone in the panel) rather
 * than blocking the write.
 *
 * @since x-release-please-version
 */
import { parse, print } from 'graphql';

const cache = new Map();
const CACHE_LIMIT = 256;

function rememberSync(query, value) {
	if (cache.size >= CACHE_LIMIT) {
		// LRU-ish: drop the oldest insertion. Map iteration is insertion-order.
		const oldest = cache.keys().next().value;
		if (oldest !== undefined) {
			cache.delete(oldest);
		}
	}
	cache.set(query, value);
	return value;
}

/**
 * Compute the sha256 hex digest of a normalized GraphQL document.
 *
 * @param {string} query Raw query string from the editor.
 * @return {Promise<string|null>} 64-char hex hash, or null if the query
 *                                isn't valid GraphQL.
 */
export async function computeOperationHash(query) {
	if (typeof query !== 'string' || query.trim() === '') {
		return null;
	}
	if (cache.has(query)) {
		return cache.get(query);
	}
	let normalized;
	try {
		normalized = print(parse(query));
	} catch {
		return rememberSync(query, null);
	}
	if (
		typeof window === 'undefined' ||
		!window.crypto ||
		!window.crypto.subtle
	) {
		return rememberSync(query, null);
	}
	const bytes = new TextEncoder().encode(normalized);
	const digest = await window.crypto.subtle.digest('SHA-256', bytes);
	const hex = Array.from(new Uint8Array(digest))
		.map((b) => b.toString(16).padStart(2, '0'))
		.join('');
	return rememberSync(query, hex);
}

/**
 * Reset the in-memory cache. Tests use this between cases so a hash
 * computed under one set of conditions doesn't leak into another.
 *
 * @return {void}
 */
export function __resetOperationHashCacheForTests() {
	cache.clear();
}
