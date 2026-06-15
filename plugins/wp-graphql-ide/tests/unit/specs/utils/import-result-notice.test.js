/* eslint-env jest */
import { importResultToNotice } from '../../../../src/utils/import-result-notice';

describe('importResultToNotice', () => {
	it('passes through the server error message on apiFetch rejection', () => {
		// `apiFetch` rejects with the decoded WP_Error body — a plain
		// object with `.code` and `.message`. We want the server's
		// message in the toast, not the generic JSON hint.
		const error = Object.assign(new Error(), {
			code: 'invalid_payload',
			message:
				'Import payload must be an object with a non-empty "collections" array.',
		});
		const notice = importResultToNotice({ error });
		expect(notice.type).toBe('error');
		expect(notice.message).toContain(
			'Import payload must be an object with a non-empty'
		);
		expect(notice.message).not.toContain('valid JSON');
	});

	it('falls back to the JSON hint for thrown errors without a `.code`', () => {
		// `JSON.parse` throws a SyntaxError with no `code`. The toast
		// should hint that the file shape is wrong, not echo a server
		// message that never existed.
		const error = new SyntaxError(
			'Unexpected token < in JSON at position 0'
		);
		const notice = importResultToNotice({ error });
		expect(notice.type).toBe('error');
		expect(notice.message).toContain('valid JSON');
	});

	it('surfaces a handler-level `result.error` as the toast', () => {
		// `ImportExport::import()` returns `{ error: '...' }` for
		// schema-version mismatches and other recoverable failures.
		// That message should pass through.
		const result = {
			created: 0,
			skipped: 0,
			collections: [],
			error: 'Unsupported import schema version 99 (this build expects version 1).',
		};
		const notice = importResultToNotice({ result });
		expect(notice.type).toBe('error');
		expect(notice.message).toContain(
			'Unsupported import schema version 99'
		);
	});

	it('renders the singular created message for one imported query', () => {
		const notice = importResultToNotice({
			result: { created: 1, skipped: 0 },
		});
		expect(notice.type).toBe('default');
		expect(notice.message).toBe('Imported 1 query.');
	});

	it('renders the plural created message for multiple', () => {
		const notice = importResultToNotice({
			result: { created: 12, skipped: 0 },
		});
		expect(notice.message).toBe('Imported 12 queries.');
	});

	it('appends the skip count when any were deduplicated', () => {
		const notice = importResultToNotice({
			result: { created: 3, skipped: 2 },
		});
		expect(notice.type).toBe('default');
		expect(notice.message).toBe(
			'Imported 3 queries (2 skipped as duplicates).'
		);
	});

	it('handles a zero-create, all-skipped result without dropping the period', () => {
		const notice = importResultToNotice({
			result: { created: 0, skipped: 4 },
		});
		expect(notice.message).toBe(
			'Imported 0 queries (4 skipped as duplicates).'
		);
	});
});
