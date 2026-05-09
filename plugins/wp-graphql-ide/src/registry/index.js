import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import hooks from '../wordpress-hooks';
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
import { QueryAnalyzerExtensionTab } from '../components/response-extensions/QueryAnalyzerExtensionTab';
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

export const initializeRegistry = () => {
	registerEditorToolbarButtons();

	registerActivityBarPanel(
		'saved-queries',
		{
			title: 'Saved Queries',
			icon: SavedQueriesIcon,
			content: SavedQueriesPanel,
			headerAction: SavedQueriesPanelHeaderAction,
		},
		1
	);

	registerActivityBarPanel(
		'docs-explorer',
		{
			title: 'Docs',
			icon: DocsExplorerIcon,
			content: DocsExplorerPanel,
		},
		5
	);

	registerActivityBarPanel(
		'history',
		{
			title: 'History',
			icon: HistoryIcon,
			content: HistoryPanel,
		},
		30
	);

	// Built-in response-extension tabs. Errors / Headers describe the
	// response envelope itself (not response.extensions), so they flag
	// alwaysShow and pull from the synthetic slots in ResponseContent.
	// Order: Errors (5) → Tracing (7) → other extension tabs → Headers
	// (80) — matches the user's most-likely consultation order:
	//   - errors when a query fails
	//   - tracing when it succeeded but felt slow
	//   - headers (auth/CORS) is a rarer destination
	registerResponseExtensionTab(
		'errors',
		{
			title: ({ data }) => `Errors (${data?.length || 0})`,
			content: ErrorsExtensionTab,
			alwaysShow: true,
		},
		5
	);

	registerResponseExtensionTab(
		'tracing',
		{
			title: 'Tracing',
			content: TracingExtensionTab,
		},
		7
	);

	registerResponseExtensionTab(
		'debug',
		{
			title: 'Debug',
			content: DebugExtensionTab,
		},
		10
	);

	registerResponseExtensionTab(
		'queryAnalyzer',
		{
			title: 'Query Analyzer',
			content: QueryAnalyzerExtensionTab,
		},
		20
	);

	registerResponseExtensionTab(
		'queryLog',
		{
			title: 'Query Log',
			content: QueryLogExtensionTab,
		},
		40
	);

	registerResponseExtensionTab(
		'headers',
		{
			title: ({ data }) => `Headers (${Object.keys(data || {}).length})`,
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
			title: 'Variables',
			content: VariablesEditorTab,
		},
		10
	);

	registerEditorBottomTab(
		'headers',
		{
			title: 'Headers',
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
			label: 'JSON',
			render: (ctx) => <FormattedViewMode {...ctx} />,
		},
		10
	);
	registerResponseViewMode(
		'table',
		{
			label: 'Table',
			render: (ctx) => <TableViewMode {...ctx} />,
		},
		20
	);

	// Built-in response-toolbar kebab actions. Same registry plugins use
	// for "Copy as cURL", "Export to Postman", etc. The `group` field
	// drives a labelled <MenuGroup> in Gutenberg's post-editor style.
	registerResponseAction(
		'show-data-only',
		{
			label: 'Show data only',
			group: 'View',
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
			label: 'Show full response',
			group: 'View',
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
	registerEditorAction(
		'share-link',
		{
			label: 'Share link…',
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
			label: 'Rename query',
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
			label: 'Duplicate as draft',
			onClick: ({ closeMenu, duplicateAsDraft }) => {
				closeMenu();
				duplicateAsDraft();
			},
			predicate: ({ endpointMode, isPublished }) =>
				!endpointMode && isPublished,
		},
		30
	);

	// Built-in document-tab kebab actions. Plugins can add "Pin tab",
	// "Lock tab", "Move all to collection", etc. through this registry.
	registerDocumentTabAction(
		'close-active',
		{
			label: 'Close active tab',
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
			label: 'Close inactive tabs',
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
			label: 'Close all tabs',
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
		title: 'Settings',
		content: SettingsWorkspaceTab,
	});
	registerWorkspacePersistence(SETTINGS_TAB_ID, {
		save: saveAllSettings,
		discard: clearPendingSettings,
	});

	// Built-in topbar actions — refresh-schema lives in the same
	// registry as Settings so plugins can drop in alongside them.
	//
	// Easter egg: if the user mash-refreshes the schema (gap < 1.5s
	// between clicks), surface a playful snackbar at preset milestones
	// — same pattern as the play-button mash in ExecutionControls.
	let schemaRefreshCount = 0;
	let lastSchemaRefreshAt = 0;
	const SCHEMA_REFRESH_RAPID_MS = 1500;
	const SCHEMA_REFRESH_MILESTONES = {
		3: "Refreshing again? It hasn't changed since 0.5s ago.",
		5: 'Trust the cache.',
		8: 'The schema is doing its best.',
		12: 'Your schema is fine, I promise.',
	};
	registerTopbarAction(
		'refresh-schema',
		{
			title: 'Re-fetch schema',
			icon: () => <Icon icon={update} />,
			onClick: ({ refetchSchema }) => {
				const now = Date.now();
				if (now - lastSchemaRefreshAt < SCHEMA_REFRESH_RAPID_MS) {
					schemaRefreshCount += 1;
				} else {
					schemaRefreshCount = 1;
				}
				lastSchemaRefreshAt = now;
				const message = SCHEMA_REFRESH_MILESTONES[schemaRefreshCount];
				if (message) {
					hooks.doAction('wpgraphql-ide.notice', message, 'default');
				}
				refetchSchema?.();
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
				title: 'WPGraphQL Settings',
				icon: () => <Icon icon={cog} />,
				tabType: 'graphql-settings',
				tabId: 'graphql-settings',
			},
			10
		);
	}
};
