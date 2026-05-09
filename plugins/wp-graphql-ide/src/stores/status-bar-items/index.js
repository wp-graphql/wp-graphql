import { createReduxStore } from '@wordpress/data';

import selectors from './status-bar-items-selectors';
import reducer from './status-bar-items-reducer';
import actions from './status-bar-items-actions';

export const store = createReduxStore('wpgraphql-ide/status-bar-items', {
	reducer,
	selectors,
	actions,
});
