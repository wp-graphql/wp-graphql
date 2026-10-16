import { createReduxStore } from '@wordpress/data';

import selectors from './settings-review-store-selectors';
import reducer from './settings-review-store-reducer';
import actions from './settings-review-store-actions';

/**
 * The store for the settings review.
 */
export const store = createReduxStore('wpgraphql/settings-review', {
	reducer,
	selectors,
	actions,
});
