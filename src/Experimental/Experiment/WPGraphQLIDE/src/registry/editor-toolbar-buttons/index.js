import { registerDocumentEditorToolbarButton } from '../../access-functions';
import { toggleAuthButton } from './toggle-auth-button';
import { prettifyButton } from './prettify-button';
import { shareButton } from './share-button';
import { mergeFragmentsButton } from './merge-fragments-button';
import { copyQueryButton } from './copy-query-button';

export const registerEditorToolbarButtons = () => {
	registerDocumentEditorToolbarButton( 'toggle-auth', toggleAuthButton, 1 );
	registerDocumentEditorToolbarButton( 'prettify', prettifyButton );
	registerDocumentEditorToolbarButton( 'share', shareButton );
	registerDocumentEditorToolbarButton(
		'merge-fragments',
		mergeFragmentsButton
	);
	registerDocumentEditorToolbarButton( 'copy-query', copyQueryButton );
};
