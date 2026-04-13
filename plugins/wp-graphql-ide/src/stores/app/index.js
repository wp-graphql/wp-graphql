import { createReduxStore } from '@wordpress/data';

import selectors from './app-store-selectors';
import reducer from './app-store-reducer';
import actions from './app-store-actions';

/**
 * The store for the app.
 */
export const store = createReduxStore('wpgraphql-ide/app', {
	reducer,
	selectors,
	actions,
});
