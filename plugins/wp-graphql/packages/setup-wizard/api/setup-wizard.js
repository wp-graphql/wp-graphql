import apiFetch from '@wordpress/api-fetch';

/**
 * Returns the bootstrap data localized for the setup wizard.
 *
 * @return {Object} The bootstrap data.
 */
export function getBootstrapData() {
	return window.wpgraphqlSetupWizard || {};
}

/**
 * Saves the setup wizard.
 *
 * @param {Object}                args
 * @param {'completed'|'skipped'} args.status     Whether the wizard was completed or skipped.
 * @param {Object}                [args.settings] The settings grouped by section, `{ section: { name: value } }`. Required when completed.
 *
 * @return {Promise<{state: Object, values: Object}>} The saved wizard state and current values.
 */
export function saveSetupWizard({ status, settings }) {
	const { restPath } = getBootstrapData();

	return apiFetch({
		path: `/${restPath}`,
		method: 'POST',
		data: settings ? { status, settings } : { status },
	});
}
