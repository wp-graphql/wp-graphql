import { __, sprintf } from '@wordpress/i18n';
import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import hooks from '../wordpress-hooks';
import { tipify } from '../utils/tipify';
import { registerExternalFragmentInjector } from '../api/external-fragments';
import {
	registerActivityBarPanel,
	registerDocumentTabAction,
	registerEditorAction,
	registerEditorBottomTab,
	registerResponseAction,
	registerResponseExtensionTab,
	registerResponseViewMode,
	registerStatusBarItem,
	registerTopbarAction,
	registerWorkspaceTabType,
} from '../access-functions';
import { Icon, cog, update } from '@wordpress/icons';
import { SettingsWorkspaceTab } from '../components/settings/SettingsWorkspaceTab';
import {
	SETTINGS_TAB_ID,
	saveAllSettings,
	clearPending as clearPendingSettings,
} from '../components/settings/settings-tab-state';
import { registerWorkspacePersistence } from '../components/workspace-persistence';
import {
	SavedQueriesPanel,
	SavedQueriesIcon,
	SavedQueriesPanelHeaderAction,
} from '../components/SavedQueriesPanel';
import {
	DocsExplorerPanel,
	DocsExplorerIcon,
} from '../components/DocsExplorerPanel';
import { HistoryPanel, HistoryIcon } from '../components/HistoryPanel';
import { DebugExtensionTab } from '../components/response-extensions/DebugExtensionTab';
import { TracingExtensionTab } from '../components/response-extensions/TracingExtensionTab';
import { QueryLogExtensionTab } from '../components/response-extensions/QueryLogExtensionTab';
import { ErrorsExtensionTab } from '../components/response-extensions/ErrorsExtensionTab';
import { HeadersExtensionTab } from '../components/response-extensions/HeadersExtensionTab';
import { VariablesEditorTab } from '../components/editor-bottom-tabs/VariablesEditorTab';
import { HeadersEditorTab } from '../components/editor-bottom-tabs/HeadersEditorTab';
import {
	StatusCodeItem,
	DurationItem,
	SizeItem,
	ResolverCountItem,
	NPlusOneItem,
} from '../components/status-bar-items/built-in-items';
import {
	FormattedViewMode,
	TableViewMode,
} from '../components/response-view-modes/built-in-modes';
import { hasSmartCache } from '../bootstrap';

export const initializeRegistry = () => {
	// Built-in executeRequest consumer: inject external fragments
	// declared via the `wpgraphql_ide_external_fragments` PHP filter
	// into any outgoing query that references them by name.
	registerExternalFragmentInjector(hooks);

	registerEditorToolbarButtons();

	// The Saved Queries panel exclusively persists through Smart Cache's
	// graphql_document post type + graphql_document_group taxonomy. With
	// Smart Cache inactive the REST routes the panel calls don't exist,
	// so don't register the panel at all rather than render a broken
	// empty state.
	if (hasSmartCache) {
		registerActivityBarPanel(
			'saved-queries',
			{
				title: __('Saved Queries', 'wpgraphql-ide'),
				icon: SavedQueriesIcon,
				content: SavedQueriesPanel,
				headerAction: SavedQueriesPanelHeaderAction,
			},
			1
		);
	}

	registerActivityBarPanel(
		'docs-explorer',
		{
			title: __('Docs', 'wpgraphql-ide'),
			icon: DocsExplorerIcon,
			content: DocsExplorerPanel,
		},
		5
	);

	registerActivityBarPanel(
		'history',
		{
			title: __('History', 'wpgraphql-ide'),
			icon: HistoryIcon,
			content: HistoryPanel,
		},
		30
	);

	// Built-in extension tabs. Errors / Headers describe the response
	// envelope (not response.extensions) so they set alwaysShow and pull
	// from synthetic slots in ResponseContent. Priority order matches the
	// usual consultation order: Errors (5) → Tracing (7) → others → Headers (80).
	registerResponseExtensionTab(
		'errors',
		{
			title: ({ data }) =>
				sprintf(
					/* translators: %d: number of errors in the response */
					__('Errors (%d)', 'wpgraphql-ide'),
					data?.length || 0
				),
			content: ErrorsExtensionTab,
			alwaysShow: true,
		},
		5
	);

	registerResponseExtensionTab(
		'tracing',
		{
			title: __('Tracing', 'wpgraphql-ide'),
			content: TracingExtensionTab,
		},
		7
	);

	registerResponseExtensionTab(
		'debug',
		{
			title: __('Debug', 'wpgraphql-ide'),
			content: DebugExtensionTab,
		},
		10
	);

	registerResponseExtensionTab(
		'queryLog',
		{
			title: __('Query Log', 'wpgraphql-ide'),
			content: QueryLogExtensionTab,
		},
		40
	);

	registerResponseExtensionTab(
		'headers',
		{
			title: ({ data }) =>
				sprintf(
					/* translators: %d: number of response headers */
					__('Headers (%d)', 'wpgraphql-ide'),
					Object.keys(data || {}).length
				),
			content: HeadersExtensionTab,
			alwaysShow: true,
		},
		80
	);

	// Built-in editor-bottom tabs (Variables / Headers). Both belong to
	// the request, not the document — published docs lock query content
	// but variables and headers stay editable per-execution.
	registerEditorBottomTab(
		'variables',
		{
			title: __('Variables', 'wpgraphql-ide'),
			content: VariablesEditorTab,
		},
		10
	);

	registerEditorBottomTab(
		'headers',
		{
			title: __('Headers', 'wpgraphql-ide'),
			content: HeadersEditorTab,
		},
		20
	);

	// Built-in response status-bar items. Render in priority order
	// (left-to-right): status code → duration → size → resolver count
	// → N+1 warning. Each render fn returns null to hide itself when
	// its data isn't relevant to the current response (no tracing →
	// no resolver / N+1 badge).
	registerStatusBarItem(
		'status-code',
		{ render: (ctx) => <StatusCodeItem {...ctx} /> },
		10
	);
	registerStatusBarItem(
		'duration',
		{ render: (ctx) => <DurationItem {...ctx} /> },
		20
	);
	registerStatusBarItem(
		'size',
		{ render: (ctx) => <SizeItem {...ctx} /> },
		30
	);
	registerStatusBarItem(
		'resolver-count',
		{ render: (ctx) => <ResolverCountItem {...ctx} /> },
		40
	);
	registerStatusBarItem(
		'n-plus-one',
		{ render: (ctx) => <NPlusOneItem {...ctx} /> },
		50
	);

	// Built-in response view modes (JSON / Table). Same registry plugins
	// can drop into for additional viewers (Diff, Schema-aware, Raw, etc.).
	registerResponseViewMode(
		'formatted',
		{
			label: __('JSON', 'wpgraphql-ide'),
			render: (ctx) => <FormattedViewMode {...ctx} />,
		},
		10
	);
	registerResponseViewMode(
		'table',
		{
			label: __('Table', 'wpgraphql-ide'),
			render: (ctx) => <TableViewMode {...ctx} />,
		},
		20
	);

	// Built-in response-toolbar kebab actions. Same registry plugins use
	// for "Copy as cURL", "Export to Postman", etc. The `group` field
	// drives a labelled <MenuGroup> in Gutenberg's post-editor style.
	const responseViewGroup = __('View', 'wpgraphql-ide');
	registerResponseAction(
		'show-data-only',
		{
			label: __('Show data only', 'wpgraphql-ide'),
			group: responseViewGroup,
			onClick: ({ setDataScope, closeMenu }) => {
				setDataScope('data');
				closeMenu();
			},
			isSelected: ({ dataScope }) => dataScope === 'data',
		},
		10
	);
	registerResponseAction(
		'show-full-response',
		{
			label: __('Show full response', 'wpgraphql-ide'),
			group: responseViewGroup,
			onClick: ({ setDataScope, closeMenu }) => {
				setDataScope('full');
				closeMenu();
			},
			isSelected: ({ dataScope }) => dataScope === 'full',
		},
		20
	);

	// Built-in editor toolbar kebab actions. The leading "registered
	// toolbar buttons" group (Prettify, etc.) still renders separately
	// from EditorToolbar — these are the document-scoped actions that
	// follow it.
	//
	// All three of these operate on a saved-document concept (share
	// links resolve a sha256-named graphql_document, rename targets the
	// post title, duplicate spawns a new draft from a published doc),
	// so they only register when Smart Cache is available.
	if (hasSmartCache) {
		registerEditorAction(
			'share-link',
			{
				label: __('Share link…', 'wpgraphql-ide'),
				onClick: ({ closeMenu, openShareDialog }) => {
					closeMenu();
					openShareDialog();
				},
				isDisabled: ({ query }) => !query?.trim(),
				predicate: ({ endpointMode }) => !endpointMode,
			},
			10
		);
		registerEditorAction(
			'rename-query',
			{
				label: __('Rename query', 'wpgraphql-ide'),
				onClick: ({ closeMenu, openRenameDialog }) => {
					closeMenu();
					openRenameDialog();
				},
				predicate: ({ endpointMode, activeDocument, isTempId }) =>
					!endpointMode &&
					!!activeDocument?.id &&
					!isTempId(activeDocument.id),
			},
			20
		);
		registerEditorAction(
			'duplicate-as-draft',
			{
				label: __('Duplicate as draft', 'wpgraphql-ide'),
				onClick: ({ closeMenu, duplicateAsDraft }) => {
					closeMenu();
					duplicateAsDraft();
				},
				predicate: ({ endpointMode, isPublished }) =>
					!endpointMode && isPublished,
			},
			30
		);
	}

	// Built-in document-tab kebab actions. Plugins can add "Pin tab",
	// "Lock tab", "Move all to collection", etc. through this registry.
	registerDocumentTabAction(
		'close-active',
		{
			label: __('Close active tab', 'wpgraphql-ide'),
			onClick: ({ activeId, onClose, closeMenu }) => {
				if (activeId) {
					onClose(String(activeId));
				}
				closeMenu();
			},
			isDisabled: ({ activeId }) => !activeId,
		},
		10
	);
	registerDocumentTabAction(
		'close-inactive',
		{
			label: __('Close inactive tabs', 'wpgraphql-ide'),
			onClick: ({ activeId, onCloseOthers, closeMenu }) => {
				onCloseOthers(activeId);
				closeMenu();
			},
			isDisabled: ({ tabs }) => tabs.length <= 1,
		},
		20
	);
	registerDocumentTabAction(
		'close-all',
		{
			label: __('Close all tabs', 'wpgraphql-ide'),
			onClick: ({ onCloseAll, closeMenu }) => {
				onCloseAll();
				closeMenu();
			},
			isDestructive: true,
		},
		30
	);

	// Built-in "Settings" workspace tab — opened from the topbar settings
	// button when the current user can manage WPGraphQL settings.
	registerWorkspaceTabType(SETTINGS_TAB_ID, {
		title: __('Settings', 'wpgraphql-ide'),
		content: SettingsWorkspaceTab,
	});
	registerWorkspacePersistence(SETTINGS_TAB_ID, {
		save: saveAllSettings,
		discard: clearPendingSettings,
	});

	// Refresh-schema action. Single click fires the baseline notice;
	// mashing (gap < 1.5s) suppresses the baseline so milestones aren't
	// drowned out by repeated "Schema refreshed" toasts. One stable id
	// lets milestones replace in place. Errors always surface.
	let schemaRefreshCount = 0;
	let lastSchemaRefreshAt = 0;
	const SCHEMA_REFRESH_RAPID_MS = 1500;
	const SCHEMA_REFRESH_NOTICE_ID = 'wpgraphql-ide-schema-refresh-mash';
	const SCHEMA_REFRESH_MILESTONES = {
		3: __(
			"Refreshing again? It hasn't changed since 0.5s ago. The schema is cached client-side — no network round-trip until you hit this button.",
			'wpgraphql-ide'
		),
		5: __(
			'Trust the cache. Refresh only after you change types or fields on the server.',
			'wpgraphql-ide'
		),
		8: __(
			'The schema is doing its best. Tip: the Docs panel always reflects the schema currently loaded in the IDE.',
			'wpgraphql-ide'
		),
		12: __(
			'Your schema is fine, I promise. Tip: enable GRAPHQL_DEBUG on the server for richer schema-introspection output.',
			'wpgraphql-ide'
		),
	};
	registerTopbarAction(
		'refresh-schema',
		{
			title: __('Re-fetch schema', 'wpgraphql-ide'),
			icon: () => <Icon icon={update} />,
			onClick: async ({ refetchSchema }) => {
				const now = Date.now();
				if (now - lastSchemaRefreshAt < SCHEMA_REFRESH_RAPID_MS) {
					schemaRefreshCount += 1;
				} else {
					schemaRefreshCount = 1;
				}
				lastSchemaRefreshAt = now;
				const milestone = SCHEMA_REFRESH_MILESTONES[schemaRefreshCount];
				if (milestone) {
					hooks.doAction(
						'wpgraphql-ide.notice',
						{
							id: SCHEMA_REFRESH_NOTICE_ID,
							content: tipify(milestone),
						},
						'default'
					);
				}

				const result = await refetchSchema?.();

				if (result && !result.ok) {
					hooks.doAction(
						'wpgraphql-ide.notice',
						sprintf(
							/* translators: %s: error message from the GraphQL schema refresh attempt */
							__('Failed to refresh schema: %s', 'wpgraphql-ide'),
							result?.error?.message ??
								__('Unknown error', 'wpgraphql-ide')
						),
						'error'
					);
				} else if (schemaRefreshCount === 1) {
					hooks.doAction(
						'wpgraphql-ide.notice',
						__('Schema refreshed', 'wpgraphql-ide'),
						'default'
					);
				}
			},
			isDisabled: ({ isSchemaLoading }) => !!isSchemaLoading,
			className: ({ isSchemaLoading }) =>
				isSchemaLoading ? 'is-loading' : '',
		},
		5
	);

	// Register the settings topbar action if the user can manage settings.
	const canManageSettings =
		typeof window !== 'undefined' &&
		!!window.WPGRAPHQL_IDE_DATA?.canManageSettings;

	if (canManageSettings) {
		registerTopbarAction(
			'graphql-settings',
			{
				title: __('WPGraphQL Settings', 'wpgraphql-ide'),
				icon: () => <Icon icon={cog} />,
				tabType: 'graphql-settings',
				tabId: 'graphql-settings',
			},
			10
		);
	}
};
