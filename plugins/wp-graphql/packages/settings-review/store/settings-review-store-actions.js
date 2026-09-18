import { __ } from '@wordpress/i18n';
import { saveSettingsReview } from '../api/settings-review';

/**
 * Groups the chosen values by settings section, leaving out locked settings.
 *
 * @param {Object[]} fields The review settings.
 * @param {Object}   values The chosen values, keyed by "{section}.{name}".
 *
 * @return {Object<string,Object>} The values as `{ section: { name: value } }`.
 */
export function groupValuesBySection(fields, values) {
	return fields.reduce((grouped, field) => {
		if (field.locked) {
			return grouped;
		}

		return {
			...grouped,
			[field.section]: {
				...(grouped[field.section] || {}),
				[field.name]: values[field.key],
			},
		};
	}, {});
}

/**
 * Saves the review with the given status and records the result.
 *
 * @param {'completed'|'skipped'} status     Whether the review was completed or skipped.
 * @param {Object}                [settings] The settings grouped by section. Required when completed.
 *
 * @return {Function} The thunk.
 */
const save =
	(status, settings) =>
	async ({ dispatch }) => {
		dispatch({ type: 'SAVE_START' });

		try {
			const response = await saveSettingsReview({ status, settings });

			dispatch({
				type: 'SAVE_SUCCESS',
				status,
				reviewState: response.state,
				unreviewedKeys: response.unreviewedKeys || [],
				values: response.values,
			});
		} catch (error) {
			dispatch({
				type: 'SAVE_ERROR',
				error:
					error?.message ||
					__('The settings review could not be saved.', 'wp-graphql'),
				fieldErrors: error?.data?.errors || {},
			});
		}
	};

/**
 * Actions for the settings review store.
 *
 * @type {Object}
 */
const actions = {
	nextStep:
		() =>
		({ dispatch, select }) => {
			dispatch({
				type: 'SET_STEP_INDEX',
				stepIndex: select.getStepIndex() + 1,
			});
		},
	previousStep:
		() =>
		({ dispatch, select }) => {
			dispatch({
				type: 'SET_STEP_INDEX',
				stepIndex: select.getStepIndex() - 1,
			});
		},
	setValue: (key, value) => ({ type: 'SET_VALUE', key, value }),
	complete:
		() =>
		async ({ dispatch, select }) => {
			await dispatch(
				save(
					'completed',
					groupValuesBySection(select.getFields(), select.getValues())
				)
			);
		},
	skip:
		() =>
		async ({ dispatch }) => {
			await dispatch(save('skipped'));
		},
	restart: () => ({ type: 'RESTART' }),
};

export default actions;
