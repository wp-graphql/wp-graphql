import { createReduxStore } from '@wordpress/data';

import selectors from './response-extensions-selectors';
import reducer from './response-extensions-reducer';
import actions from './response-extensions-actions';

export const store = createReduxStore('wpgraphql-ide/response-extensions', {
	reducer,
	selectors,
	actions,
});
