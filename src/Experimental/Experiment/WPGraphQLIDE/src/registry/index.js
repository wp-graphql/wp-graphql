import { registerEditorToolbarButtons } from './editor-toolbar-buttons';
import { registerActivityBarPanels } from './activity-bar-panels';

export const initializeRegistry = () => {
	registerEditorToolbarButtons();
	registerActivityBarPanels();
};
