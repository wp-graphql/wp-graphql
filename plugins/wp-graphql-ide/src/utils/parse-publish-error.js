/**
 * Recognize Smart Cache's content-addressed publish collision.
 *
 * Smart Cache treats the sha256 of a normalized GraphQL document as the
 * published `graphql_document`'s identity (the `graphql_query_alias`
 * taxonomy term). Two drafts with identical content can coexist, but
 * publishing the second one fails because the alias term is already
 * attached to the first published doc.
 *
 * The server message has the shape:
 *
 *   Alias "<sha256>" already in use by another query "<title>"
 *
 * Returns `{ alias, conflictTitle }` when the message matches, or `null`
 * for any other error. Callers use this to surface an actionable notice
 * ("Open existing") instead of the raw 64-char hash.
 *
 * @param {string} message
 * @return {{ alias: string, conflictTitle: string } | null}
 */
export function parseAliasInUseError(message) {
	if (typeof message !== 'string' || !message) {
		return null;
	}
	const match = message.match(
		/Alias\s+"([a-f0-9]{64})"\s+already in use by another query\s+"([^"]+)"/i
	);
	if (!match) {
		return null;
	}
	return { alias: match[1], conflictTitle: match[2] };
}
