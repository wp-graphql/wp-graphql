import { createReduxStore } from '@wordpress/data';

import selectors from './editor-bottom-tabs-selectors';
import reducer from './editor-bottom-tabs-reducer';
import actions from './editor-bottom-tabs-actions';

export const store = createReduxStore('wpgraphql-ide/editor-bottom-tabs', {
	reducer,
	selectors,
	actions,
});
