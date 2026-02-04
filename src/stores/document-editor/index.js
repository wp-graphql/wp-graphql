import { createReduxStore } from '@wordpress/data';

import selectors from './document-editor-store-selectors';
import reducer from './document-editor-store-reducer';
import actions from './document-editor-store-actions';

/**
 * The store for the app.
 */
export const store = createReduxStore( 'wpgraphql-ide/document-editor', {
	reducer,
	selectors,
	actions,
} );
