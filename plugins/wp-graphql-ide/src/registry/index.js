import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import { registerActivityBarPanel } from '../access-functions';
import { HistoryPanel, HistoryIcon } from '../components/HistoryPanel';

export const initializeRegistry = () => {
	registerEditorToolbarButtons();

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
