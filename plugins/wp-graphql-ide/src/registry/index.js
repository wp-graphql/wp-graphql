import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import {
	registerActivityBarPanel,
	registerResponseExtensionTab,
} from '../access-functions';
import { DocumentsPanel, DocumentsIcon } from '../components/DocumentsPanel';
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
		'documents',
		{
			title: 'Documents',
			icon: DocumentsIcon,
			content: DocumentsPanel,
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
};
