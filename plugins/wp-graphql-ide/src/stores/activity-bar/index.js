import { createReduxStore } from '@wordpress/data';

import selectors from './activity-bar-selectors';
import reducer from './activity-bar-reducer';
import actions from './activity-bar-actions';

/**
 * The store for the app.
 */
export const store = createReduxStore('wpgraphql-ide/activity-bar', {
	reducer,
	selectors,
	actions,
});
