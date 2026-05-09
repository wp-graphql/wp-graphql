import { register } from '@wordpress/data';

import { store as appStore } from './app';
import { store as documentEditorStore } from './document-editor';
import { store as activityBarStore } from './activity-bar';
import { store as responseExtensionsStore } from './response-extensions';
import { store as editorBottomTabsStore } from './editor-bottom-tabs';
import { store as statusBarItemsStore } from './status-bar-items';
import { store as responseViewModesStore } from './response-view-modes';
import { store as responseActionsStore } from './response-actions';
import { store as editorActionsStore } from './editor-actions';

export function registerStores() {
	register(appStore);
	register(documentEditorStore);
	register(activityBarStore);
	register(responseExtensionsStore);
	register(editorBottomTabsStore);
	register(statusBarItemsStore);
	register(responseViewModesStore);
	register(responseActionsStore);
	register(editorActionsStore);
}
