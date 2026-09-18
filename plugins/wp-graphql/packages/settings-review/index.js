import { createRoot, render } from '@wordpress/element';
import { register } from '@wordpress/data';

import { store } from './store';
import { SettingsReview } from './components/SettingsReview';
import './index.scss';

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('wpgraphql-settings-review');

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
		createRoot(container).render(<SettingsReview />);
	} else {
		render(<SettingsReview />, container);
	}
});
