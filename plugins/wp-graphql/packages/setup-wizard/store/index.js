import { createReduxStore } from '@wordpress/data';

import selectors from './setup-wizard-store-selectors';
import reducer from './setup-wizard-store-reducer';
import actions from './setup-wizard-store-actions';

/**
 * The store for the setup wizard.
 */
export const store = createReduxStore('wpgraphql/setup-wizard', {
	reducer,
	selectors,
	actions,
});
