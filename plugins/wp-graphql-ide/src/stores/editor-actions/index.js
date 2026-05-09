import { createReduxStore } from '@wordpress/data';

import selectors from './editor-actions-selectors';
import reducer from './editor-actions-reducer';
import actions from './editor-actions-actions';

export const store = createReduxStore('wpgraphql-ide/editor-actions', {
	reducer,
	selectors,
	actions,
});
