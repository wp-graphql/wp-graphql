import { createReduxStore } from '@wordpress/data';

import selectors from './response-view-modes-selectors';
import reducer from './response-view-modes-reducer';
import actions from './response-view-modes-actions';

export const store = createReduxStore('wpgraphql-ide/response-view-modes', {
	reducer,
	selectors,
	actions,
});
