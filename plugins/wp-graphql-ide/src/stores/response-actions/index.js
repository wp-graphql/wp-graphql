import { createReduxStore } from '@wordpress/data';

import selectors from './response-actions-selectors';
import reducer from './response-actions-reducer';
import actions from './response-actions-actions';

export const store = createReduxStore('wpgraphql-ide/response-actions', {
	reducer,
	selectors,
	actions,
});
