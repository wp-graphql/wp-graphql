/**
 * Behavioural coverage for the `?wpgraphql_ide=` deep-link share
 * restore. The Share dialog packs `{ query, variables?, headers? }`
 * into an LZString-compressed URL param; `loadDocuments` must decode it
 * and open it as a new active tab (not write it to the app store, which
 * the IDELayout tab-switch sync would clobber).
 *
 * The document-editor actions module talks to REST + localStorage on
 * boot, so those I/O seams are mocked to no-ops — we only care about how
 * the share param drives `createTab`.
 */
import LZString from 'lz-string';
import { WELCOME_QUERY } from '../../../../src/stores/document-editor/welcome-query';

jest.mock('../../../../src/api/documents', () => ({
	getDocuments: jest.fn(() => Promise.resolve([])),
	createDocument: jest.fn(),
	updateDocument: jest.fn(),
	deleteDocument: jest.fn(),
	reorderDocuments: jest.fn(),
}));
jest.mock('../../../../src/api/preferences', () => ({
	getPreferences: jest.fn(() => Promise.resolve({})),
	setPreference: jest.fn(),
}));
jest.mock(
	'../../../../src/stores/document-editor/unsaved-tabs-storage',
	() => ({
		getUnsavedTabs: jest.fn(() => []),
		saveUnsavedTab: jest.fn(),
		removeUnsavedTab: jest.fn(),
	})
);
jest.mock(
	'../../../../src/stores/document-editor/legacy-graphiql-tabs-migration',
	() => ({
		migrateLegacyTabs: jest.fn(() => Promise.resolve()),
	})
);

import actions from '../../../../src/stores/document-editor/document-editor-store-actions';

const BASE_URL = '/wp-admin/admin.php?page=graphql-ide';

const setUrlParam = (encoded) =>
	window.history.replaceState(
		{},
		'',
		encoded === null ? BASE_URL : `${BASE_URL}&wpgraphql_ide=${encoded}`
	);

const encodeShare = (payload) =>
	LZString.compressToEncodedURIComponent(JSON.stringify(payload));

const makeDispatch = () => {
	const dispatch = jest.fn();
	dispatch.createTab = jest.fn();
	dispatch.persistTabState = jest.fn();
	return dispatch;
};

// `loadDocuments` reads workspace-tab + active-tab state via `select` so
// it can preserve a tab the user opened while hydrate was in flight.
// These tests boot from an empty store, so both selectors return the
// "nothing open yet" shape.
const makeSelect = () => ({
	getOpenTabObjects: jest.fn(() => []),
	getActiveTab: jest.fn(() => null),
});

const runLoad = async () => {
	const dispatch = makeDispatch();
	const select = makeSelect();
	await actions.loadDocuments()({ dispatch, select });
	return dispatch;
};

describe('loadDocuments — share-link restore', () => {
	beforeEach(() => {
		jest.clearAllMocks();
		setUrlParam(null);
	});

	it('opens a shared link as a single new tab with its query/variables/headers', async () => {
		const payload = {
			query: 'query Shared { posts { nodes { id } } }',
			variables: '{"first":3}',
			headers: '{"x-test":"1"}',
		};
		setUrlParam(encodeShare(payload));

		const dispatch = await runLoad();

		expect(dispatch.createTab).toHaveBeenCalledTimes(1);
		const [title, query, variables, headers] =
			dispatch.createTab.mock.calls[0];
		expect(title).toBe('');
		// Normalized via parse → print, so assert on content not exact text.
		expect(query).toContain('query Shared');
		expect(query).toContain('posts');
		expect(variables).toBe('{"first":3}');
		expect(headers).toBe('{"x-test":"1"}');
	});

	it('defaults missing variables/headers to empty strings', async () => {
		setUrlParam(encodeShare({ query: '{ posts { nodes { id } } }' }));

		const dispatch = await runLoad();

		const [, , variables, headers] = dispatch.createTab.mock.calls[0];
		expect(variables).toBe('');
		expect(headers).toBe('');
	});

	it('strips the wpgraphql_ide param from the URL after restoring', async () => {
		setUrlParam(encodeShare({ query: '{ posts { nodes { id } } }' }));

		await runLoad();

		expect(window.location.search).not.toContain('wpgraphql_ide');
	});

	it('does not re-open the shared tab on a second load (param already stripped)', async () => {
		setUrlParam(encodeShare({ query: '{ posts { nodes { id } } }' }));

		await runLoad();
		const second = await runLoad();

		// Second boot sees no share param and no tabs → welcome tab.
		expect(second.createTab).toHaveBeenCalledTimes(1);
		expect(second.createTab.mock.calls[0][1]).toBe(WELCOME_QUERY);
	});

	it('falls back to the welcome tab when there is no share param', async () => {
		const dispatch = await runLoad();

		expect(dispatch.createTab).toHaveBeenCalledTimes(1);
		expect(dispatch.createTab.mock.calls[0]).toEqual(['', WELCOME_QUERY]);
	});

	it('falls back to the welcome tab when the share param is undecodable', async () => {
		setUrlParam('not-a-valid-lzstring-payload');

		const dispatch = await runLoad();

		expect(dispatch.createTab).toHaveBeenCalledTimes(1);
		expect(dispatch.createTab.mock.calls[0][1]).toBe(WELCOME_QUERY);
		// Bad param is still cleared so it can't keep re-firing.
		expect(window.location.search).not.toContain('wpgraphql_ide');
	});
});
