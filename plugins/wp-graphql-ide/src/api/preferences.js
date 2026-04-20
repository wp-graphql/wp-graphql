import apiFetch from '@wordpress/api-fetch';

const META_PREFIX = 'wpgraphql_ide_';
const USER_ENDPOINT = '/wp/v2/users/me';

/**
 * Fetch all IDE preferences for the current user.
 *
 * @return {Promise<Object>} Preference key-value pairs (without prefix).
 */
export async function getPreferences() {
	const user = await apiFetch({
		path: `${USER_ENDPOINT}?_fields=meta`,
	});

	const meta = user?.meta || {};
	const prefs = {};

	for (const [key, value] of Object.entries(meta)) {
		if (key.startsWith(META_PREFIX)) {
			prefs[key.replace(META_PREFIX, '')] = value;
		}
	}

	return prefs;
}

/**
 * Save a single IDE preference for the current user.
 *
 * @param {string} key   Preference name (without prefix).
 * @param {*}      value Preference value.
 * @return {Promise<Object>} Updated user response.
 */
export async function savePreference(key, value) {
	return apiFetch({
		path: USER_ENDPOINT,
		method: 'POST',
		data: {
			meta: {
				[`${META_PREFIX}${key}`]: value,
			},
		},
	});
}

/**
 * Save multiple IDE preferences at once.
 *
 * @param {Object} prefs Key-value pairs (without prefix).
 * @return {Promise<Object>} Updated user response.
 */
export async function savePreferences(prefs) {
	const meta = {};
	for (const [key, value] of Object.entries(prefs)) {
		meta[`${META_PREFIX}${key}`] = value;
	}

	return apiFetch({
		path: USER_ENDPOINT,
		method: 'POST',
		data: { meta },
	});
}
