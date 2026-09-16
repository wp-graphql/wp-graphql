import { createRoot, render } from '@wordpress/element';
import { register } from '@wordpress/data';

import { store } from './store';
import { SetupWizard } from './components/SetupWizard';
import './index.scss';

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('wpgraphql-setup-wizard');

	if (!container) {
		return;
	}

	register(store);

	/**
	 * createRoot only exists in WordPress 6.2+.
	 *
	 * Once that version is the minimum required, this check can be removed.
	 */
	if (createRoot) {
		createRoot(container).render(<SetupWizard />);
	} else {
		render(<SetupWizard />, container);
	}
});
