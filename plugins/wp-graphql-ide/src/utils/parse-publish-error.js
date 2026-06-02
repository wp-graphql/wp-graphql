/**
 * Recognize Smart Cache's content-addressed collision errors on save /
 * publish.
 *
 * Smart Cache treats the sha256 of a normalized GraphQL document as the
 * `graphql_document`'s identity (the `graphql_query_alias` taxonomy
 * term). It rejects writes that would create two documents with the
 * same content. Two related messages come out of the server:
 *
 *   Alias "<sha256>" already in use by another query "<title>"
 *     — fires when a publish mutation's input alias collides with the
 *       term attached to another doc.
 *
 *   This query has already been associated with another query "<title>"
 *     — fires from `valid_or_throw` on any save (draft or publish) when
 *       the new content's hash matches a term already attached to a
 *       different doc.
 *
 * Both surfaces the same UX outcome — point at the existing doc — so
 * the parser collapses them into one shape.
 *
 * Returns `{ alias, conflictTitle }` when the message matches either
 * form (`alias` is null for the content-collision case since the
 * server doesn't include it), or `null` for any other error. Callers
 * use this to surface an actionable notice ("Open existing") instead
 * of the raw error.
 *
 * @param {string} message
 * @return {{ alias: string|null, conflictTitle: string } | null}
 */
export function parseAliasInUseError(message) {
	if (typeof message !== 'string' || !message) {
		return null;
	}
	const aliasMatch = message.match(
		/Alias\s+"([a-f0-9]{64})"\s+already in use by another query\s+"([^"]+)"/i
	);
	if (aliasMatch) {
		return { alias: aliasMatch[1], conflictTitle: aliasMatch[2] };
	}
	const contentMatch = message.match(
		/This query has already been associated with another query\s+"([^"]+)"/i
	);
	if (contentMatch) {
		return { alias: null, conflictTitle: contentMatch[1] };
	}
	return null;
}
