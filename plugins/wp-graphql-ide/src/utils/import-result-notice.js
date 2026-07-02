import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Decide which snackbar message to show after an import attempt.
 *
 * Three cases:
 *  - **Thrown error from `apiFetch`** — the server rejected the request
 *    (HTTP 4xx / 5xx). `apiFetch` surfaces a WP_Error-shaped object with
 *    `.code` and `.message`. We pass the server's message through so the
 *    user sees the actual cause ("Import payload must be an object with
 *    a non-empty `collections` array.", etc.) instead of a generic hint.
 *  - **Thrown error without `.code`** — almost always `JSON.parse`
 *    failing on a malformed file. The generic "valid JSON" hint helps.
 *  - **Resolved with a payload** — the handler ran. A `result.error`
 *    field surfaces handler-level errors (e.g. unsupported schema
 *    version); otherwise we render the created/skipped counts.
 *
 * Returns `{ message, type }` so callers can pass straight to
 * `addNotice(message, type)` or `notify(message, type)`.
 *
 * @param {Object} args
 * @param {Object} [args.result] Resolved response from `importDocuments`.
 * @param {Error}  [args.error]  Thrown error, if `importDocuments` rejected.
 * @return {{ message: string, type: 'default'|'error' }}
 *
 * @since x-release-please-version
 */
export function importResultToNotice({ result, error }) {
	if (error) {
		// `apiFetch` rejects with the decoded WP_Error body, which has a
		// `.code` field. SyntaxError from `JSON.parse` doesn't. Use that
		// to tell server failures apart from local parse failures.
		const isApiError =
			typeof error.code === 'string' &&
			typeof error.message === 'string' &&
			error.message !== '';
		if (isApiError) {
			return {
				message: sprintf(
					/* translators: %s: error message returned by the import endpoint */
					__('Import failed: %s', 'wpgraphql-ide'),
					error.message
				),
				type: 'error',
			};
		}
		return {
			message: __(
				'Import failed. Make sure the file is valid JSON.',
				'wpgraphql-ide'
			),
			type: 'error',
		};
	}

	if (result?.error) {
		return {
			message: sprintf(
				/* translators: %s: error message returned by the import endpoint */
				__('Import failed: %s', 'wpgraphql-ide'),
				result.error
			),
			type: 'error',
		};
	}

	const created = Number(result?.created) || 0;
	const skipped = Number(result?.skipped) || 0;

	const createdMsg = sprintf(
		/* translators: %d: number of queries imported */
		_n(
			'Imported %d query.',
			'Imported %d queries.',
			created,
			'wpgraphql-ide'
		),
		created
	);

	if (!skipped) {
		return { message: createdMsg, type: 'default' };
	}

	return {
		message:
			sprintf(
				/* translators: 1: created-count sentence, 2: number of duplicate queries skipped */
				__('%1$s (%2$d skipped as duplicates)', 'wpgraphql-ide'),
				createdMsg.replace(/\.$/, ''),
				skipped
			) + '.',
		type: 'default',
	};
}
