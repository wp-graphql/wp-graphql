import { register } from '@wordpress/data';

import { store as appStore } from './app';
import { store as documentEditorStore } from './document-editor';
import { store as activityBarStore } from './activity-bar';

export function registerStores() {
	register( appStore );
	register( documentEditorStore );
	register( activityBarStore );
}
