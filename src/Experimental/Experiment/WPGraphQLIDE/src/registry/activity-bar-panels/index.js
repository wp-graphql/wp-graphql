import { registerActivityBarPanel } from '../../access-functions';
import { helpPanel } from './helpPanel';

export const registerActivityBarPanels = () => {
	registerActivityBarPanel( 'help', helpPanel, 1 );
};
