import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import {
	registerActivityBarPanel,
	registerResponseExtensionTab,
	registerWorkspaceTabType,
} from '../access-functions';
import { SettingsWorkspaceTab } from '../components/settings/SettingsWorkspaceTab';
import {
	SavedQueriesPanel,
	SavedQueriesIcon,
	SavedQueriesHeaderAction,
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

export const initializeRegistry = () => {
	registerEditorToolbarButtons();

	registerActivityBarPanel(
		'saved-queries',
		{
			title: 'Saved Queries',
			icon: SavedQueriesIcon,
			content: SavedQueriesPanel,
			headerAction: SavedQueriesHeaderAction,
		},
		5
	);

	registerActivityBarPanel(
		'docs-explorer',
		{
			title: 'Docs',
			icon: DocsExplorerIcon,
			content: DocsExplorerPanel,
		},
		1
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

	// Built-in response extension tabs for extensions shipped with WPGraphQL core.
	// Other extensions (e.g. wp-graphql-smart-cache) register their own tabs.
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
		'tracing',
		{
			title: 'Tracing',
			content: TracingExtensionTab,
		},
		30
	);

	registerResponseExtensionTab(
		'queryLog',
		{
			title: 'Query Log',
			content: QueryLogExtensionTab,
		},
		40
	);

	// Built-in "Settings" workspace tab — opened from the topbar settings
	// button when the current user can manage WPGraphQL settings.
	registerWorkspaceTabType('graphql-settings', {
		title: 'Settings',
		content: SettingsWorkspaceTab,
	});
};
