/**
 * Execution-history client.
 *
 * History is browser-local for every visitor — admin or anonymous,
 * logged-in or signed-out. Buckets are scoped per (WordPress user, IDE
 * context) so an admin signing in on the same browser as another admin
 * gets their own history, and the admin-IDE bucket stays distinct
 * from the public-endpoint bucket. The implementation lives in
 * `./history-local.js`; this file is a thin re-export so callsites
 * import history operations by their generic name.
 *
 * @since x-release-please-version
 */

export {
	getLocalHistory as getHistory,
	createLocalHistoryEntry as createHistoryEntry,
	deleteLocalHistoryEntry as deleteHistoryEntry,
	clearLocalHistory as clearHistory,
} from './history-local';
