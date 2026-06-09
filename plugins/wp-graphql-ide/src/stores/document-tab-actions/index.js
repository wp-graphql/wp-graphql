import { createReduxStore } from '@wordpress/data';

import selectors from './document-tab-actions-selectors';
import reducer from './document-tab-actions-reducer';
import actions from './document-tab-actions-actions';

export const store = createReduxStore('wpgraphql-ide/document-tab-actions', {
	reducer,
	selectors,
	actions,
});
