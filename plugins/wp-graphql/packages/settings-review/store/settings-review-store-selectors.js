/**
 * Selectors for the settings review store.
 *
 * Settings are identified by their key, "{section}.{name}".
 *
 * @type {Object}
 */
const selectors = {
	getStepIndex: (state) => state.stepIndex,
	getStepSlugs: (state) => state.stepSlugs,
	getCurrentStepSlug: (state) => state.stepSlugs[state.stepIndex],
	getRegisteredSteps: (state) => state.registeredSteps,
	getRegisteredStep: (state, slug) =>
		state.registeredSteps.find((step) => step.slug === slug) || null,
	getFields: (state) => state.fields,
	getField: (state, key) =>
		state.fields.find((field) => field.key === key) || null,
	getValues: (state) => state.values,
	getValue: (state, key) => state.values[key],
	getSavedValues: (state) => state.savedValues,
	getSavedValue: (state, key) => state.savedValues[key],
	getUnreviewedKeys: (state) => state.unreviewedKeys,
	getReviewState: (state) => state.reviewState,
	isSaving: (state) => state.isSaving,
	getFinishedStatus: (state) => state.finishedStatus,
	getError: (state) => state.error,
	getFieldError: (state, key) => state.fieldErrors[key],
	getFieldErrors: (state) => state.fieldErrors,
};

export default selectors;
