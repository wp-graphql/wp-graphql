import { registerDocumentEditorToolbarButton } from '../../access-functions';
import { prettifyButton } from './prettify-button';
import { shareButton } from './share-button';
import { mergeFragmentsButton } from './merge-fragments-button';
import { copyQueryButton } from './copy-query-button';

export const registerEditorToolbarButtons = () => {
	// toggle-auth is rendered directly in the header next to Execute.
	registerDocumentEditorToolbarButton('prettify', prettifyButton);
	registerDocumentEditorToolbarButton('share', shareButton);
	registerDocumentEditorToolbarButton(
		'merge-fragments',
		mergeFragmentsButton
	);
	registerDocumentEditorToolbarButton('copy-query', copyQueryButton);
};
