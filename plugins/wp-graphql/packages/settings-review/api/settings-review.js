import apiFetch from '@wordpress/api-fetch';

/**
 * Returns the bootstrap data localized for the settings review.
 *
 * @return {Object} The bootstrap data.
 */
export function getBootstrapData() {
	return window.wpgraphqlSettingsReview || {};
}

/**
 * Saves the settings review.
 *
 * @param {Object}                args
 * @param {'completed'|'skipped'} args.status     Whether the review was completed or skipped.
 * @param {Object}                [args.settings] The settings grouped by section, `{ section: { name: value } }`. Required when completed.
 *
 * @return {Promise<{state: Object, values: Object}>} The saved review state and current values.
 */
export function saveSettingsReview({ status, settings }) {
	const { restPath } = getBootstrapData();

	return apiFetch({
		path: `/${restPath}`,
		method: 'POST',
		data: settings ? { status, settings } : { status },
	});
}
