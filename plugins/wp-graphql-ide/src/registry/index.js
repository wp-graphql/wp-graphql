import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import {
	registerActivityBarPanel,
	registerEditorBottomTab,
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
	registerTopbarAction(
		'refresh-schema',
		{
			title: 'Re-fetch schema',
			icon: () => <Icon icon={update} />,
			onClick: ({ refetchSchema }) => refetchSchema?.(),
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
