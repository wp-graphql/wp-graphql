import { getBootstrapData } from '../api/setup-wizard';

/**
 * The step shown before the registered steps.
 *
 * @type {string}
 */
export const INTRO_STEP = 'intro';

/**
 * The step shown after the registered steps.
 *
 * @type {string}
 */
export const SUMMARY_STEP = 'summary';

/**
 * Builds the initial state from the localized bootstrap data.
 *
 * Settings are keyed by "{section}.{name}".
 *
 * @return {Object} The initial state.
 */
function getInitialState() {
	const data = getBootstrapData();
	const values = data.values || {};
	const registeredSteps = data.steps || [];

	return {
		stepIndex: 0,
		// The slugs of every step in order, including the intro and summary steps.
		stepSlugs: [
			INTRO_STEP,
			...registeredSteps.map((step) => step.slug),
			SUMMARY_STEP,
		],
		registeredSteps,
		fields: data.fields || [],
		values: { ...values },
		// The values as last saved, used to show what saving will change.
		savedValues: { ...values },
		// Settings added since the wizard was last completed or skipped.
		unreviewedKeys: data.unreviewedKeys || [],
		wizardState: data.state || {},
		isSaving: false,
		// 'completed' | 'skipped' | null, set after a successful save in this session.
		finishedStatus: null,
		error: null,
		fieldErrors: {},
	};
}

/**
 * The reducer for the setup wizard store.
 *
 * @param {Object} state  The current state.
 * @param {Object} action The dispatched action.
 *
 * @return {Object} The next state.
 */
const reducer = (state = getInitialState(), action) => {
	switch (action.type) {
		case 'SET_STEP_INDEX':
			return {
				...state,
				stepIndex: Math.max(
					0,
					Math.min(state.stepSlugs.length - 1, action.stepIndex)
				),
			};
		case 'SET_VALUE':
			return {
				...state,
				values: { ...state.values, [action.key]: action.value },
				fieldErrors: { ...state.fieldErrors, [action.key]: undefined },
			};
		case 'SAVE_START':
			return { ...state, isSaving: true, error: null, fieldErrors: {} };
		case 'SAVE_SUCCESS':
			return {
				...state,
				isSaving: false,
				finishedStatus: action.status,
				wizardState: action.wizardState,
				unreviewedKeys: action.unreviewedKeys,
				values: { ...action.values },
				savedValues: { ...action.values },
			};
		case 'SAVE_ERROR':
			return {
				...state,
				isSaving: false,
				error: action.error,
				fieldErrors: action.fieldErrors || {},
			};
		case 'RESTART':
			return {
				...state,
				stepIndex: 0,
				finishedStatus: null,
				error: null,
			};
		default:
			return state;
	}
};

export default reducer;
