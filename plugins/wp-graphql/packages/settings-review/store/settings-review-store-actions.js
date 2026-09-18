import { __ } from '@wordpress/i18n';
import { saveSettingsReview } from '../api/settings-review';

/**
 * Groups the settings the administrator changed by settings section, leaving out locked settings.
 *
 * Only changed settings are sent, so settings that weren't changed keep their current stored value.
 *
 * @param {Object[]} fields      The settings in the review.
 * @param {Object}   values      The chosen values, keyed by "{section}.{name}".
 * @param {Object}   savedValues The saved values, keyed by "{section}.{name}".
 *
 * @return {Object<string,Object>} The changed values as `{ section: { name: value } }`.
 */
export function groupChangedValuesBySection(fields, values, savedValues) {
	return fields.reduce((grouped, field) => {
		if (
			field.locked ||
			String(values[field.key]) === String(savedValues[field.key])
		) {
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
					groupChangedValuesBySection(
						select.getFields(),
						select.getValues(),
						select.getSavedValues()
					)
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
