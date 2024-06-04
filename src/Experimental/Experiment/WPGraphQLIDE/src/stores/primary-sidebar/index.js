import { createReduxStore } from '@wordpress/data';

import selectors from './primary-sidebar-selectors';
import reducer from './primary-sidebar-reducer';
import actions from './primary-sidebar-actions';

/**
 * The store for the primary sidebar.
 */
export const store = createReduxStore( 'wpgraphql-ide/primary-sidebar', {
	reducer,
	selectors,
	actions,
} );
