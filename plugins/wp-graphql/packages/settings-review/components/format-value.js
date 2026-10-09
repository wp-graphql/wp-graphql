import { __ } from '@wordpress/i18n';

/**
 * Formats a setting value for display.
 *
 * @param {Object}                       field The setting.
 * @param {string|number|null|undefined} value The value to format.
 *
 * @return {string} The formatted value.
 */
export function formatValue(field, value) {
	if (value === null || value === undefined || value === '') {
		return __('Not set', 'wp-graphql');
	}

	if ('checkbox' === field?.type) {
		return 'on' === value
			? __('On', 'wp-graphql')
			: __('Off', 'wp-graphql');
	}

	const option = (field?.options || []).find(
		(choice) => choice.value === String(value)
	);

	return option ? option.label : String(value);
}

/**
 * Returns the keys of the settings whose chosen value differs from the saved value.
 *
 * @param {Object[]} fields      The settings.
 * @param {Object}   values      The chosen values.
 * @param {Object}   savedValues The saved values.
 *
 * @return {string[]} The changed setting keys.
 */
export function getChangedKeys(fields, values, savedValues) {
	return fields
		.map((field) => field.key)
		.filter((key) => String(values[key]) !== String(savedValues[key]));
}
