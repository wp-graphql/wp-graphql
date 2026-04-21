import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import { registerActivityBarPanel } from '../access-functions';
import { DocumentsPanel, DocumentsIcon } from '../components/DocumentsPanel';
import { HistoryPanel, HistoryIcon } from '../components/HistoryPanel';

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
		'history',
		{
			title: 'History',
			icon: HistoryIcon,
			content: HistoryPanel,
		},
		30
	);
};
